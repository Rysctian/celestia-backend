<?php

namespace Tests\Feature;

use App\Models\AttendancePolicy;
use App\Models\Employee;
use App\Models\EmployeeLog;
use App\Models\EmployeeSchedule;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\ScheduleDetail;
use App\Models\User;
use App\Services\Attendance\AttendanceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendancePolicyTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $roleCode = 'admin'): User
    {
        $employee = Employee::factory()->create();
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->forEmployee($employee)->create(['role_id' => $role->id]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function policyPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Standard Policy',
            'description' => '15-minute tardy grace counted from schedule start.',
            'tardy_grace_seconds' => 900,
            'tardy_count_from' => AttendancePolicy::COUNT_FROM_SCHEDULE_START,
            'absent_after_late_seconds' => 3600,
            'undertime_grace_seconds' => 600,
            'early_in_window_seconds' => 3600,
            'late_out_window_seconds' => 7200,
            'duplicate_tap_window_seconds' => 300,
            'is_default' => false,
            'is_active' => true,
        ], $overrides);
    }

    private function scheduleTemplate(array $overrides = []): array
    {
        $details = [];
        foreach (range(1, 5) as $day) {
            $details[] = [
                'day_of_week' => $day,
                'is_rest_day' => false,
                'start_time' => '08:00',
                'end_time' => '12:00',
            ];
            $details[] = [
                'day_of_week' => $day,
                'is_rest_day' => false,
                'start_time' => '13:00',
                'end_time' => '17:00',
            ];
        }
        foreach ([6, 7] as $day) {
            $details[] = [
                'day_of_week' => $day,
                'is_rest_day' => true,
                'start_time' => null,
                'end_time' => null,
            ];
        }

        return array_merge([
            'name' => 'Office Shift',
            'timezone' => 'Asia/Manila',
            'details' => $details,
        ], $overrides);
    }

    public function test_attendance_policy_permissions_and_crud(): void
    {
        $this->actingAsRole('employee');
        $this->getJson('/api/attendance-policies')->assertForbidden();
        $this->postJson('/api/attendance-policies', $this->policyPayload())->assertForbidden();

        $this->actingAsRole('admin');

        $created = $this->postJson('/api/attendance-policies', $this->policyPayload())
            ->assertCreated()
            ->assertJsonPath('message', 'Attendance policy created successfully.')
            ->assertJsonPath('data.name', 'Standard Policy')
            ->assertJsonPath('data.tardy_grace_seconds', 900);

        $policyId = $created->json('data.id');

        $this->getJson('/api/attendance-policies')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $policyId);

        $this->getJson("/api/attendance-policies/{$policyId}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Standard Policy');

        $this->getJson('/api/attendance-policies/999999')->assertNotFound();

        $this->putJson("/api/attendance-policies/{$policyId}", [
            'name' => 'Updated Policy',
            'tardy_grace_seconds' => 600,
            'tardy_count_from' => AttendancePolicy::COUNT_FROM_AFTER_GRACE,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated Policy')
            ->assertJsonPath('data.tardy_grace_seconds', 600)
            ->assertJsonPath('data.tardy_count_from', AttendancePolicy::COUNT_FROM_AFTER_GRACE);

        $this->deleteJson("/api/attendance-policies/{$policyId}")
            ->assertOk()
            ->assertJsonPath('message', 'Attendance policy deleted successfully.');

        $this->assertDatabaseMissing('attendance_policies', ['id' => $policyId]);
    }

    public function test_attendance_policy_search_filter_sort_and_pagination(): void
    {
        $this->actingAsRole('admin');

        AttendancePolicy::factory()->create([
            'name' => 'Beta Night Policy',
            'description' => 'After grace threshold for night staff',
            'tardy_count_from' => AttendancePolicy::COUNT_FROM_AFTER_GRACE,
            'is_default' => false,
            'is_active' => true,
        ]);

        AttendancePolicy::factory()->create([
            'name' => 'Alpha Day Policy',
            'description' => 'Standard grace for office staff',
            'tardy_count_from' => AttendancePolicy::COUNT_FROM_SCHEDULE_START,
            'is_default' => true,
            'is_active' => true,
        ]);

        AttendancePolicy::factory()->create([
            'name' => 'Gamma Legacy Policy',
            'description' => 'Retired policy',
            'tardy_count_from' => AttendancePolicy::COUNT_FROM_SCHEDULE_START,
            'is_default' => false,
            'is_active' => false,
        ]);

        $this->getJson('/api/attendance-policies?search=night')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Beta Night Policy');

        $this->getJson('/api/attendance-policies?is_active=1&tardy_count_from=schedule_start')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alpha Day Policy');

        $this->getJson('/api/attendance-policies?is_default=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alpha Day Policy');

        $this->getJson('/api/attendance-policies?sort_by=name&sort_order=asc&limit=2&offset=1')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Beta Night Policy')
            ->assertJsonPath('data.1.name', 'Gamma Legacy Policy');
    }

    public function test_default_policy_promotion_and_guards(): void
    {
        $this->actingAsRole('admin');

        $firstId = $this->postJson('/api/attendance-policies', $this->policyPayload([
            'name' => 'First Default',
            'is_default' => true,
        ]))->assertCreated()->json('data.id');

        $secondId = $this->postJson('/api/attendance-policies', $this->policyPayload([
            'name' => 'Second Default',
            'is_default' => true,
        ]))->assertCreated()->json('data.id');

        $this->assertFalse(AttendancePolicy::findOrFail($firstId)->is_default);
        $this->assertTrue(AttendancePolicy::findOrFail($secondId)->is_default);

        // Inactive policy cannot be set as default.
        $this->postJson('/api/attendance-policies', $this->policyPayload([
            'name' => 'Inactive Default',
            'is_default' => true,
            'is_active' => false,
        ]))->assertUnprocessable();

        // absent_after_late_seconds must exceed tardy_grace_seconds.
        $this->postJson('/api/attendance-policies', $this->policyPayload([
            'name' => 'Invalid Absent Threshold',
            'tardy_grace_seconds' => 900,
            'absent_after_late_seconds' => 900,
        ]))->assertUnprocessable();

        // Cannot deactivate or delete the sole default policy.
        $this->putJson("/api/attendance-policies/{$secondId}", ['is_active' => false])->assertUnprocessable();
        $this->deleteJson("/api/attendance-policies/{$secondId}")->assertUnprocessable();

        // Assign first policy to a schedule; deleting it must be blocked.
        Schedule::factory()->withWeek()->create(['attendance_policy_id' => $firstId]);
        $this->deleteJson("/api/attendance-policies/{$firstId}")->assertUnprocessable();
    }

    public function test_schedule_can_link_attendance_policy_and_eager_load_it(): void
    {
        $this->actingAsRole('admin');

        $policy = AttendancePolicy::factory()->create(['name' => 'Flexible Grace']);
        $otherPolicy = AttendancePolicy::factory()->create(['name' => 'Strict Policy']);

        $scheduleId = $this->postJson('/api/schedules', $this->scheduleTemplate([
            'attendance_policy_id' => $policy->id,
        ]))->assertCreated()
            ->assertJsonPath('data.attendance_policy_id', $policy->id)
            ->assertJsonPath('data.attendance_policy.id', $policy->id)
            ->assertJsonPath('data.attendance_policy.name', 'Flexible Grace')
            ->json('data.id');

        $this->putJson("/api/schedules/{$scheduleId}", [
            'attendance_policy_id' => $otherPolicy->id,
        ])->assertOk()
            ->assertJsonPath('data.attendance_policy_id', $otherPolicy->id)
            ->assertJsonPath('data.attendance_policy.name', 'Strict Policy');

        $this->postJson('/api/schedules', $this->scheduleTemplate([
            'name' => 'Broken Policy Link',
            'attendance_policy_id' => 999999,
        ]))->assertUnprocessable();
    }

    public function test_attendance_evaluation_uses_policy_grace_and_thresholds(): void
    {
        $admin = $this->actingAsRole('admin');

        // Subcase A: Within 15-min tardy grace => tardy_seconds = 0.
        $gracePolicy = AttendancePolicy::factory()->create([
            'name' => 'Grace 15m Schedule Start',
            'tardy_grace_seconds' => 900,
            'tardy_count_from' => AttendancePolicy::COUNT_FROM_SCHEDULE_START,
            'absent_after_late_seconds' => 3600,
            'undertime_grace_seconds' => 600,
        ]);

        $schedule = Schedule::factory()->withWeek()->create(['attendance_policy_id' => $gracePolicy->id]);
        EmployeeSchedule::factory()->create([
            'employee_id' => $admin->employee_id,
            'schedule_id' => $schedule->id,
            'effective_from' => '2026-10-01',
        ]);

        // Monday 2026-10-12: clock in at 08:10 (within 15m grace), out at 12:00.
        EmployeeLog::factory()->create(['employee_id' => $admin->employee_id, 'logged_at' => '2026-10-12 08:10:00']);
        EmployeeLog::factory()->create(['employee_id' => $admin->employee_id, 'logged_at' => '2026-10-12 12:00:00']);

        $this->getJson('/api/employee-attendance?'.http_build_query([
            'employee_id' => $admin->employee_id,
            'from' => '2026-10-12',
            'to' => '2026-10-12',
        ]))->assertOk()
            ->assertJsonPath('data.0.tardy_seconds', 0)
            ->assertJsonPath('data.0.absent', false);

        // Subcase B: Exceeds grace (08:20) with count_from = schedule_start => 1200s late.
        EmployeeLog::factory()->create(['employee_id' => $admin->employee_id, 'logged_at' => '2026-10-13 08:20:00']);
        EmployeeLog::factory()->create(['employee_id' => $admin->employee_id, 'logged_at' => '2026-10-13 12:00:00']);

        $this->getJson('/api/employee-attendance?'.http_build_query([
            'employee_id' => $admin->employee_id,
            'from' => '2026-10-13',
            'to' => '2026-10-13',
        ]))->assertOk()
            ->assertJsonPath('data.0.tardy_seconds', 1200);

        // Subcase C: Switch policy to count_from = after_grace => 08:20 yields 300s late.
        $gracePolicy->update(['tardy_count_from' => AttendancePolicy::COUNT_FROM_AFTER_GRACE]);

        $this->getJson('/api/employee-attendance?'.http_build_query([
            'employee_id' => $admin->employee_id,
            'from' => '2026-10-13',
            'to' => '2026-10-13',
        ]))->assertOk()
            ->assertJsonPath('data.0.tardy_seconds', 300);

        // Subcase D: Exceeds absent_after_late_seconds (3600s) at 09:05 => marked absent.
        EmployeeLog::factory()->create(['employee_id' => $admin->employee_id, 'logged_at' => '2026-10-14 09:05:00']);
        EmployeeLog::factory()->create(['employee_id' => $admin->employee_id, 'logged_at' => '2026-10-14 12:00:00']);

        $this->getJson('/api/employee-attendance?'.http_build_query([
            'employee_id' => $admin->employee_id,
            'from' => '2026-10-14',
            'to' => '2026-10-14',
        ]))->assertOk()
            ->assertJsonPath('data.0.absent', true)
            ->assertJsonPath('data.0.rendered_seconds', 0)
            ->assertJsonPath('data.0.tardy_seconds', 0)
            ->assertJsonPath('data.0.undertime_seconds', 14400)
            ->assertJsonPath('data.0.remarks', 'absent');

        // Subcase E: Undertime grace period (600s) on PM slot (13:00-17:00) via AttendanceCalculator.
        $calculator = app(AttendanceCalculator::class);
        $pmSlot = ScheduleDetail::where('schedule_id', $schedule->id)
            ->where('day_of_week', 1)
            ->where('start_time', '13:00')
            ->get();

        $withinUndertimeGraceLogs = collect([
            new EmployeeLog(['logged_at' => '2026-10-12 13:00:00']),
            new EmployeeLog(['logged_at' => '2026-10-12 16:55:00']),
        ]);
        $withinResult = $calculator->calculate('2026-10-12', $pmSlot, $withinUndertimeGraceLogs, $gracePolicy);
        $this->assertSame(0, $withinResult['undertime_seconds']);

        $pastUndertimeGraceLogs = collect([
            new EmployeeLog(['logged_at' => '2026-10-12 13:00:00']),
            new EmployeeLog(['logged_at' => '2026-10-12 16:40:00']),
        ]);
        $pastResult = $calculator->calculate('2026-10-12', $pmSlot, $pastUndertimeGraceLogs, $gracePolicy);
        $this->assertSame(1200, $pastResult['undertime_seconds']);
    }

    public function test_default_policy_fallback_and_slot_override_precedence(): void
    {
        $admin = $this->actingAsRole('admin');

        AttendancePolicy::factory()->defaultPolicy()->create([
            'name' => 'Default Fallback Policy',
            'tardy_grace_seconds' => 900,
            'tardy_count_from' => AttendancePolicy::COUNT_FROM_SCHEDULE_START,
            'undertime_grace_seconds' => 600,
        ]);

        // Schedule with null attendance_policy_id falls back to active default policy.
        $schedule = Schedule::factory()->withWeek()->create(['attendance_policy_id' => null]);
        EmployeeSchedule::factory()->create([
            'employee_id' => $admin->employee_id,
            'schedule_id' => $schedule->id,
            'effective_from' => '2026-10-01',
        ]);

        // Monday 2026-10-12: 08:10 falls inside default 15-min grace.
        EmployeeLog::factory()->create(['employee_id' => $admin->employee_id, 'logged_at' => '2026-10-12 08:10:00']);
        EmployeeLog::factory()->create(['employee_id' => $admin->employee_id, 'logged_at' => '2026-10-12 12:00:00']);

        $this->getJson('/api/employee-attendance?'.http_build_query([
            'employee_id' => $admin->employee_id,
            'from' => '2026-10-12',
            'to' => '2026-10-12',
        ]))->assertOk()->assertJsonPath('data.0.tardy_seconds', 0);

        // Slot-level tardy_start override (08:05:00) supersedes policy tardy_grace_seconds (900s).
        $morningSlot = ScheduleDetail::where('schedule_id', $schedule->id)
            ->where('day_of_week', 1)
            ->where('start_time', '08:00')
            ->firstOrFail();
        $morningSlot->tardy_start = '08:05:00';
        $morningSlot->save();

        $this->getJson('/api/employee-attendance?'.http_build_query([
            'employee_id' => $admin->employee_id,
            'from' => '2026-10-12',
            'to' => '2026-10-12',
        ]))->assertOk()->assertJsonPath('data.0.tardy_seconds', 600);
    }
}
