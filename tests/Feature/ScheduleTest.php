<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Schedule;
use App\Models\User;
use Database\Seeders\EmployeeScheduleSeeder;
use Database\Seeders\ScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    }

    private function login(bool $admin = true): User
    {
        $employee = Employee::factory()->create(['type' => $admin ? 'admin' : 'employee']);
        $user = User::factory()->forEmployee($employee)->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function template(): array
    {
        return [
            'name' => 'Office',
            'timezone' => 'Asia/Manila',
            'details' => collect(range(1, 7))->flatMap(fn ($day) => $day >= 6
                ? [[
                    'day_of_week' => $day, 'is_rest_day' => true,
                    'start_time' => null, 'end_time' => null, 'ends_next_day' => false,
                ]]
                : [[
                    'day_of_week' => $day, 'is_rest_day' => false,
                    'start_time' => '08:00', 'end_time' => '12:00', 'ends_next_day' => false,
                ], [
                    'day_of_week' => $day, 'is_rest_day' => false,
                    'start_time' => '13:00', 'end_time' => '17:00', 'ends_next_day' => false,
                ]])->all(),
        ];
    }

    public function test_admin_can_create_read_and_update_an_unused_template(): void
    {
        $this->login();
        $id = $this->postJson('/api/schedules', $this->template())->assertCreated()
            ->assertJsonCount(12, 'data.details')
            ->assertJsonPath('data.details.0.code_day', 'M')
            ->assertJsonPath('data.details.1.code_day', 'M')
            ->assertJsonPath('data.details.10.code_day', 'S')
            ->assertJsonPath('data.details.11.code_day', 'SUN')->json('data.id');
        $this->getJson('/api/schedules')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/schedules/$id")->assertOk()->assertJsonPath('data.name', 'Office');
        $this->putJson("/api/schedules/$id", [...$this->template(), 'name' => 'Office Updated'])
            ->assertOk()->assertJsonPath('data.name', 'Office Updated')->assertJsonCount(12, 'data.details')
            ->assertJsonPath('data.details.6.code_day', 'TH');
        $this->assertDatabaseCount('schedule_details', 12);
        $this->getJson('/api/schedules/999999')->assertNotFound();
    }

    public function test_code_day_is_generated_and_tracks_weekday_changes(): void
    {
        $schedule = Schedule::factory()->withWeek()->create();

        $this->assertSame(['M', 'M', 'T', 'T', 'W', 'W', 'TH', 'TH', 'F', 'F', 'S', 'SUN'], $schedule->details()->pluck('code_day')->all());

        $schedule->details()->where('day_of_week', 7)->delete();
        $schedule->details()->where('day_of_week', 1)->first()->update(['day_of_week' => 7]);
        $this->assertDatabaseHas('schedule_details', [
            'schedule_id' => $schedule->id, 'day_of_week' => 7, 'code_day' => 'SUN',
        ]);
    }

    public function test_split_shifts_are_saved_and_resolved_without_counting_the_gap(): void
    {
        $user = $this->login();
        $scheduleId = $this->postJson('/api/schedules', $this->template())->assertCreated()->json('data.id');
        $this->assertDatabaseHas('schedule_details', [
            'schedule_id' => $scheduleId, 'day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '12:00',
        ]);
        $this->assertDatabaseHas('schedule_details', [
            'schedule_id' => $scheduleId, 'day_of_week' => 1, 'start_time' => '13:00', 'end_time' => '17:00',
        ]);

        $this->postJson('/api/employee-schedules', [
            'employee_ids' => [$user->employee_id],
            'schedule_id' => $scheduleId,
            'effective_from' => '2026-10-12',
        ])->assertCreated();

        $this->getJson("/api/employees/{$user->employee_id}/schedule?from=2026-10-12&to=2026-10-12")
            ->assertOk()
            ->assertJsonPath('data.0.status', 'working')
            ->assertJsonCount(2, 'data.0.shifts')
            ->assertJsonPath('data.0.shifts.0.starts_at', '2026-10-12T08:00:00+08:00')
            ->assertJsonPath('data.0.shifts.0.ends_at', '2026-10-12T12:00:00+08:00')
            ->assertJsonPath('data.0.shifts.1.starts_at', '2026-10-12T13:00:00+08:00')
            ->assertJsonPath('data.0.shifts.1.ends_at', '2026-10-12T17:00:00+08:00')
            ->assertJsonPath('data.0.scheduled_minutes', 480);
    }

    public function test_weekday_can_have_more_than_two_slots(): void
    {
        $this->login();
        $template = $this->template();
        $template['details'][0]['end_time'] = '10:00';
        $template['details'][] = [
            'day_of_week' => 1, 'is_rest_day' => false,
            'start_time' => '10:30', 'end_time' => '12:00', 'ends_next_day' => false,
        ];

        $id = $this->postJson('/api/schedules', $template)->assertCreated()
            ->assertJsonCount(13, 'data.details')->json('data.id');
        $this->assertSame(3, Schedule::findOrFail($id)->details()->where('day_of_week', 1)->count());
    }

    public function test_invalid_weeks_and_shift_times_are_rejected(): void
    {
        $this->login();
        $cases = [];
        $data = $this->template();
        array_pop($data['details']);
        $cases[] = $data;
        $data = $this->template();
        $data['details'][1]['start_time'] = '11:00';
        $cases[] = $data;
        foreach ([['start_time' => null], ['end_time' => '07:00'], ['end_time' => '08:00'], ['end_time' => '08:00', 'ends_next_day' => true]] as $change) {
            $data = $this->template();
            $data['details'][0] = [...$data['details'][0], ...$change];
            $cases[] = $data;
        }
        $data = $this->template();
        $data['details'][10]['start_time'] = '08:00';
        $cases[] = $data;
        $data = $this->template();
        $data['details'][] = $data['details'][10];
        $cases[] = $data;
        foreach ($cases as $data) {
            $this->postJson('/api/schedules', $data)->assertUnprocessable();
        }
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_sunday_overnight_cannot_overlap_monday(): void
    {
        $this->login();
        $data = $this->template();
        $data['details'][11] = [
            'day_of_week' => 7, 'is_rest_day' => false,
            'start_time' => '22:00', 'end_time' => '09:00',
            'ends_next_day' => true,
        ];
        $this->postJson('/api/schedules', $data)->assertUnprocessable()->assertJsonValidationErrors('details');
    }

    public function test_bulk_assignment_uses_current_actor_and_rejects_overlap_atomically(): void
    {
        $admin = $this->login();
        $employees = Employee::factory(2)->create();
        $schedule = Schedule::factory()->withWeek()->create();
        $payload = ['employee_ids' => $employees->pluck('employee_id')->all(), 'schedule_id' => $schedule->id, 'effective_from' => '2026-10-12', 'effective_to' => '2026-10-31', 'assigned_by' => 999];
        $this->postJson('/api/employee-schedules', $payload)->assertCreated()
            ->assertJsonCount(2, 'data')->assertJsonPath('data.0.assigned_by', $admin->id);

        $new = Employee::factory()->create();
        $payload['employee_ids'] = [$new->employee_id, $employees->last()->employee_id];
        $payload['effective_from'] = '2026-10-31';
        $payload['effective_to'] = null;
        $this->postJson('/api/employee-schedules', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('employee_schedules', 2);
        $this->assertDatabaseMissing('employee_schedules', ['employee_id' => $new->employee_id]);
    }

    public function test_open_assignment_can_be_ended_before_assigning_a_new_schedule(): void
    {
        $this->login();
        $assignment = EmployeeSchedule::factory()->create(['effective_from' => '2026-10-01']);
        $this->putJson("/api/employee-schedules/{$assignment->id}", ['effective_to' => '2026-10-31'])
            ->assertOk()->assertJsonPath('data.effective_to', '2026-10-31');
        $this->postJson('/api/employee-schedules', [
            'employee_ids' => [$assignment->employee_id], 'schedule_id' => $assignment->schedule_id,
            'effective_from' => '2026-11-01',
        ])->assertCreated();
        $this->putJson("/api/employee-schedules/{$assignment->id}", ['effective_to' => '2026-11-01'])
            ->assertUnprocessable();
        $this->assertSame('2026-10-31', $assignment->fresh()->effective_to->toDateString());
        $this->getJson("/api/employees/{$assignment->employee_id}/schedules")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_past_assignment_dates_cannot_be_rewritten(): void
    {
        $this->login();
        $assignment = EmployeeSchedule::factory()->create(['effective_from' => '2026-09-01', 'effective_to' => '2026-09-30']);
        $this->putJson("/api/employee-schedules/{$assignment->id}", ['effective_to' => '2026-10-31'])->assertUnprocessable();
    }

    public function test_used_templates_are_immutable_but_can_be_archived(): void
    {
        $this->login();
        $assignment = EmployeeSchedule::factory()->create();
        $this->putJson("/api/schedules/{$assignment->schedule_id}", ['details' => $this->template()['details']])->assertUnprocessable();
        $this->putJson("/api/schedules/{$assignment->schedule_id}", ['timezone' => 'UTC'])->assertUnprocessable();
        $this->putJson("/api/schedules/{$assignment->schedule_id}", ['is_active' => false])->assertOk();
        $new = Employee::factory()->create();
        $this->postJson('/api/employee-schedules', [
            'employee_ids' => [$new->employee_id], 'schedule_id' => $assignment->schedule_id, 'effective_from' => '2026-10-12',
        ])->assertUnprocessable();
        $this->getJson("/api/employees/{$assignment->employee_id}/schedule?from=2026-10-12&to=2026-10-12")
            ->assertOk()->assertJsonPath('data.0.status', 'working');
    }

    public function test_resolution_distinguishes_unassigned_rest_and_overnight_work(): void
    {
        $user = $this->login(false);
        $night = Schedule::factory()->withWeek(true)->create();
        EmployeeSchedule::factory()->create([
            'employee_id' => $user->employee_id, 'schedule_id' => $night->id,
            'effective_from' => '2026-10-16', 'effective_to' => '2026-10-18',
        ]);
        $this->getJson("/api/employees/{$user->employee_id}/schedule?from=2026-10-15&to=2026-10-18")
            ->assertOk()->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.status', 'unassigned')
            ->assertJsonPath('data.1.shifts.0.starts_at', '2026-10-16T22:00:00+08:00')
            ->assertJsonPath('data.1.shifts.0.ends_at', '2026-10-17T07:00:00+08:00')
            ->assertJsonPath('data.1.scheduled_minutes', 540)
            ->assertJsonPath('data.2.status', 'rest_day')
            ->assertJsonCount(0, 'data.2.shifts');
    }

    public function test_shift_overlap_is_rejected_across_assignment_boundaries(): void
    {
        $this->login();
        $night = Schedule::factory()->withWeek(true)->create();
        $assignment = EmployeeSchedule::factory()->create([
            'schedule_id' => $night->id, 'effective_from' => '2026-10-12', 'effective_to' => '2026-10-12',
        ]);
        $morning = Schedule::factory()->withWeek()->create();
        $morning->details()->where('start_time', '08:00')->update(['start_time' => '06:00']);
        $this->postJson('/api/employee-schedules', [
            'employee_ids' => [$assignment->employee_id], 'schedule_id' => $morning->id,
            'effective_from' => '2026-10-13',
        ])->assertUnprocessable()->assertJsonValidationErrors('schedule_id');
        $this->assertDatabaseCount('employee_schedules', 1);
    }

    public function test_employees_can_only_read_their_own_schedule_and_cannot_promote_themselves(): void
    {
        $user = $this->login(false);
        $other = Employee::factory()->create();
        $this->getJson("/api/employees/{$user->employee_id}/schedule?from=2026-10-12&to=2026-10-12")->assertOk();
        $this->getJson("/api/employees/{$user->employee_id}/schedules")->assertOk();
        $this->getJson("/api/employees/{$other->employee_id}/schedule?from=2026-10-12&to=2026-10-12")->assertForbidden();
        $this->getJson("/api/employees/{$other->employee_id}/schedules")->assertForbidden();
        $this->getJson('/api/schedules')->assertForbidden();
        $this->postJson('/api/schedules', $this->template())->assertForbidden();
        $this->postJson('/api/employee-schedules', [])->assertForbidden();
        $this->putJson('/api/employee-schedules/1', [])->assertForbidden();
        $this->putJson("/api/employees/{$user->employee_id}", ['type' => 'admin'])->assertForbidden();
        $this->postJson('/api/employees', ['type' => 'admin'])->assertForbidden();
    }

    public function test_date_ranges_and_assignment_inputs_are_validated(): void
    {
        $user = $this->login();
        $this->getJson("/api/employees/{$user->employee_id}/schedule?from=2026-01-01&to=2028-01-01")->assertUnprocessable();
        $this->getJson("/api/employees/{$user->employee_id}/schedule?from=2026-10-12&to=2026-10-01")->assertUnprocessable();
        $this->postJson('/api/employee-schedules', [
            'employee_ids' => ['missing'], 'schedule_id' => 999,
            'effective_from' => '2026-10-12', 'effective_to' => '2026-10-01',
        ])->assertUnprocessable()->assertJsonValidationErrors(['employee_ids.0', 'schedule_id', 'effective_to']);
    }

    public function test_fresh_database_seeding_assigns_every_generated_employee_and_can_sync_new_employees(): void
    {
        $this->seed();
        $this->assertDatabaseCount('employees', 10);
        $this->assertDatabaseCount('schedules', 2);
        $this->assertDatabaseCount('schedule_details', 19);
        $this->assertDatabaseCount('employee_schedules', 10);
        $this->assertSame(0, Employee::whereDoesntHave('schedules')->count());
        $this->assertTrue(User::where('email', 'admin@example.com')->firstOrFail()->can('manage-schedules'));

        $employee = Employee::factory()->create();
        $this->seed([ScheduleSeeder::class, EmployeeScheduleSeeder::class]);
        $this->assertDatabaseCount('schedules', 2);
        $this->assertDatabaseCount('schedule_details', 19);
        $this->assertDatabaseCount('employee_schedules', 11);
        $this->assertDatabaseHas('employee_schedules', ['employee_id' => $employee->employee_id]);
    }

    public function test_schedule_endpoints_require_authentication(): void
    {
        $this->getJson('/api/schedules')->assertUnauthorized();
        $this->postJson('/api/employee-schedules')->assertUnauthorized();
        $this->getJson('/api/employees/EMP-20260001/schedule?from=2026-10-01&to=2026-10-02')->assertUnauthorized();
    }
}
