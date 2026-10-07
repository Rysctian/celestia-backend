<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceCutoffSummary;
use App\Models\AttendanceException;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLog;
use App\Models\EmployeeSchedule;
use App\Models\OvertimeApplication;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Attendance\EmployeeLogService;
use Database\Seeders\AttendanceCorrectionSeeder;
use Database\Seeders\AttendanceExceptionSeeder;
use Database\Seeders\AttendanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private Schedule $schedule;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 20)->startOfDay());
        $this->admin = User::factory()->forEmployee(Employee::factory()->create())->create(['role_id' => Role::where('code', 'admin')->value('id')]);
        Sanctum::actingAs($this->admin);
        $this->employee = Employee::factory()->create();
        $this->schedule = Schedule::factory()->withWeek()->create();
        EmployeeSchedule::factory()->create([
            'employee_id' => $this->employee->employee_id, 'schedule_id' => $this->schedule->id,
            'effective_from' => '2026-10-01',
        ]);
    }

    private function taps(array $times, string $date = '2026-10-06'): void
    {
        foreach ($times as $time) {
            app(EmployeeLogService::class)->capture([
                'employee_id' => $this->employee->employee_id,
                'logged_at' => str_contains($time, 'T') ? $time : $date.'T'.$time.'+08:00',
                'source' => 'test',
            ]);
        }
    }

    private function process(string $date = '2026-10-06')
    {
        return $this->postJson('/api/employee-attendance/process', ['employee_id' => $this->employee->employee_id, 'date' => $date]);
    }

    private function cutoff(string $from = '2026-10-06', string $to = '2026-10-06')
    {
        return $this->postJson('/api/attendance-cutoffs', ['employee_id' => $this->employee->employee_id, 'date_from' => $from, 'date_to' => $to]);
    }

    private function blocks(array $times, int $weekday = 2): void
    {
        $this->schedule->details()->where('day_of_week', $weekday)->delete();
        foreach ($times as [$start, $end]) {
            $this->schedule->details()->create(['day_of_week' => $weekday, 'is_rest_day' => false, 'start_time' => $start, 'end_time' => $end]);
        }
    }

    public function test_raw_capture_is_unclassified_immutable_and_idempotent_with_an_external_id(): void
    {
        $tap = [
            'employee_id' => $this->employee->employee_id, 'logged_at' => '2026-10-06T07:58:04+08:00',
            'source' => 'biometric', 'device_id' => 'lobby', 'external_log_id' => 'tap-123',
            'raw_payload' => ['original' => 'unchanged'],
        ];
        $id = $this->postJson('/api/employee-logs', $tap)->assertCreated()->json('data.id');
        $this->postJson('/api/employee-logs', $tap)->assertOk()->assertJsonPath('data.id', $id);
        $this->postJson('/api/employee-logs', [...$tap, 'logged_at' => '2026-10-06T08:30:00+08:00'])->assertUnprocessable();
        $this->postJson('/api/employee-logs', [...$tap, 'external_log_id' => 'other', 'log_type' => 'in'])->assertUnprocessable();
        $this->postJson('/api/employee-logs', [...$tap, 'logged_at' => '2026-10-06 08:00:00'])->assertUnprocessable();
        $before = EmployeeLog::first()->getAttributes();
        $this->process()->assertOk();
        $this->assertSame($before, EmployeeLog::first()->getAttributes());
        $this->assertDatabaseCount('employee_logs', 1);
        $this->assertSame(['original' => 'unchanged'], EmployeeLog::first()->raw_payload);
    }

    public function test_split_blocks_use_schedule_context_for_an_odd_number_of_taps(): void
    {
        $this->blocks([['08:00', '10:00'], ['10:30', '12:00'], ['13:00', '17:00']]);
        $this->taps(['07:58:00', '10:02:00', '10:31:00', '12:01:00', '17:03:00']);
        $this->process()->assertOk()->assertJsonCount(3, 'data.attendance')
            ->assertJsonPath('data.attendance.0.status', 'present')
            ->assertJsonPath('data.attendance.1.tardy_seconds', 60)
            ->assertJsonPath('data.attendance.2.status', 'incomplete')
            ->assertJsonPath('data.attendance.2.time_in', null)
            ->assertJsonPath('data.attendance.2.missing_time_in', true);
        $this->assertSame(['in', 'out', 'in', 'out', 'out'], Timesheet::orderBy('logged_at')->pluck('log_type')->all());
        $this->cutoff()->assertCreated()->assertJsonPath('data.incomplete_days', 1)->assertJsonPath('data.present_days', 0);
    }

    public function test_duplicate_taps_from_multiple_devices_are_retained_for_audit(): void
    {
        $this->taps(['07:58:04', '07:59:10', '12:01:02', '13:05:15', '18:02:44']);
        EmployeeLog::orderBy('id')->first()->update(['device_id' => 'lobby']);
        EmployeeLog::orderBy('id')->skip(1)->first()->update(['device_id' => 'office']);
        $this->process()->assertOk();
        $this->assertDatabaseCount('employee_logs', 5);
        $this->assertDatabaseCount('timesheets', 5);
        $this->assertSame(1, Timesheet::where('status', 'duplicate')->whereNull('log_type')->count());
        $this->assertSame(4, Timesheet::where('status', 'selected')->count());
    }

    public function test_no_logs_create_absent_blocks_without_fabricating_attendance(): void
    {
        $this->process()->assertOk()->assertJsonCount(2, 'data.attendance')
            ->assertJsonPath('data.attendance.0.status', 'absent')
            ->assertJsonPath('data.attendance.0.time_in', null)
            ->assertJsonPath('data.attendance.0.time_out', null);
        $this->cutoff()->assertCreated()->assertJsonPath('data.scheduled_seconds', 28800)
            ->assertJsonPath('data.absent_days', 1)->assertJsonPath('data.rendered_seconds', 0);
    }

    public function test_one_tap_can_be_a_missing_in_or_missing_out(): void
    {
        $this->taps(['17:03:00']);
        $this->process()->assertOk()->assertJsonPath('data.attendance.1.missing_time_in', true)
            ->assertJsonPath('data.attendance.1.missing_time_out', false);
        $this->taps(['08:02:00'], '2026-10-07');
        $this->process('2026-10-07')->assertOk()->assertJsonPath('data.attendance.0.missing_time_in', false)
            ->assertJsonPath('data.attendance.0.missing_time_out', true);
    }

    public function test_late_and_undertime_are_integer_seconds_and_days_are_counted_once(): void
    {
        $this->taps(['08:07:35', '11:40:00', '13:00:00', '17:00:00']);
        $this->process()->assertOk()->assertJsonPath('data.attendance.0.tardy_seconds', 455)
            ->assertJsonPath('data.attendance.0.undertime_seconds', 1200);
        $this->cutoff()->assertCreated()->assertJsonPath('data.late_seconds', 455)
            ->assertJsonPath('data.undertime_seconds', 1200)->assertJsonPath('data.present_days', 1)
            ->assertJsonPath('data.regular_seconds', 27145);
    }

    public function test_very_early_and_late_taps_and_ambiguous_gap_taps(): void
    {
        $this->taps(['04:00:00', '12:00:00', '12:30:00', '13:00:00', '23:00:00']);
        $this->process()->assertOk()->assertJsonPath('data.attendance.0.time_in', '2026-10-05T20:00:00.000000Z')
            ->assertJsonPath('data.attendance.1.time_out', '2026-10-06T15:00:00.000000Z');
        $this->assertSame('unmatched', Timesheet::where('logged_at', '2026-10-06 04:30:00')->firstOrFail()->status);
    }

    public function test_overnight_processing_owns_cross_midnight_taps_without_stealing_next_day_logs(): void
    {
        $this->blocks([['22:00', '06:00']]);
        $this->taps(['21:55:00']);
        $this->taps(['06:05:00', '08:00:00', '12:00:00', '13:00:00', '17:00:00'], '2026-10-07');
        // Processing the next date first must not take the preceding overnight timeout.
        $this->process('2026-10-07')->assertOk();
        $this->process()->assertOk()->assertJsonPath('data.attendance.0.sched_end', '2026-10-06T22:00:00.000000Z')
            ->assertJsonPath('data.attendance.0.time_out', '2026-10-06T22:05:00.000000Z');
        $this->cutoff()->assertCreated()->assertJsonPath('data.scheduled_seconds', 28800)
            ->assertJsonPath('data.night_diff_seconds', 28800);
        $this->assertSame(1, Timesheet::whereDate('attendance_date', '2026-10-06')->where('log_type', 'out')->count());
    }

    public function test_rest_and_unassigned_days_do_not_create_false_absence(): void
    {
        $this->taps(['08:00:00'], '2026-10-10');
        $this->process('2026-10-10')->assertOk()->assertJsonPath('data.status', 'rest_day')->assertJsonCount(0, 'data.attendance');
        $this->process('2026-09-30')->assertOk()->assertJsonPath('data.status', 'unassigned')->assertJsonCount(0, 'data.attendance');
        $this->assertDatabaseCount('employee_attendance', 0);
        $this->assertDatabaseHas('timesheets', ['status' => 'unmatched']);
    }

    public function test_approved_exceptions_survive_reprocessing_and_avoid_false_absence(): void
    {
        foreach (['leave', 'holiday', 'official_business', 'suspension', 'schedule_cancellation'] as $type) {
            $this->postJson('/api/attendance-exceptions', [
                'employee_id' => $this->employee->employee_id, 'date' => '2026-10-06', 'type' => $type,
                'reason' => 'HR verified '.$type, 'approved_by' => 999,
            ])->assertCreated()->assertJsonPath('data.approved_by', $this->admin->id);
            $this->process()->assertOk()->assertJsonPath('data.attendance.0.status', $type)->assertJsonPath('data.attendance.0.absent', false);
            $this->cutoff()->assertCreated()->assertJsonPath('data.absent_days', 0)
                ->assertJsonPath('data.credited_seconds', in_array($type, ['suspension', 'schedule_cancellation']) ? 0 : 28800);
        }
        $this->assertDatabaseCount('attendance_exceptions', 1);
    }

    public function test_only_rendered_approved_overtime_is_credited_and_overlapping_approvals_are_merged(): void
    {
        $this->taps(['08:00:00', '12:00:00', '13:00:00', '19:00:00']);
        OvertimeApplication::factory()->approved()->create([
            'employee_id' => $this->employee->employee_id, 'overtime_date' => '2026-10-06', 'time_from' => '17:00', 'time_to' => '18:00',
        ]);
        OvertimeApplication::factory()->approved()->create([
            'employee_id' => $this->employee->employee_id, 'overtime_date' => '2026-10-06', 'time_from' => '17:30', 'time_to' => '18:30',
        ]);
        OvertimeApplication::factory()->create([
            'employee_id' => $this->employee->employee_id, 'overtime_date' => '2026-10-06', 'time_from' => '18:30', 'time_to' => '19:00',
        ]);
        $this->cutoff()->assertCreated()->assertJsonPath('data.overtime_rendered_seconds', 7200)
            ->assertJsonPath('data.overtime_approved_seconds', 5400)->assertJsonPath('data.credited_seconds', 34200);
    }

    public function test_overtime_application_supplies_context_on_a_rest_day_and_approval_refreshes_the_summary(): void
    {
        $overtime = OvertimeApplication::factory()->create([
            'employee_id' => $this->employee->employee_id, 'overtime_date' => '2026-10-10', 'time_from' => '08:00', 'time_to' => '12:00',
        ]);
        $this->taps(['08:00:00', '12:00:00'], '2026-10-10');
        $this->cutoff('2026-10-10', '2026-10-10')->assertCreated()->assertJsonPath('data.scheduled_seconds', 0)
            ->assertJsonPath('data.overtime_rendered_seconds', 14400)->assertJsonPath('data.overtime_approved_seconds', 0)
            ->assertJsonPath('data.present_days', 0);
        $this->postJson('/api/attendance-overtime/'.$overtime->id.'/approve')->assertOk()->assertJsonPath('data.approved_by', $this->admin->id);
        $this->assertSame(14400, AttendanceCutoffSummary::firstOrFail()->overtime_approved_seconds);
        $this->assertDatabaseCount('employee_attendance', 1);
    }

    public function test_manual_corrections_are_audited_survive_reprocessing_and_preserve_raw_logs(): void
    {
        $this->taps(['08:00:00', '12:00:00', '13:02:00']);
        $id = $this->process()->assertOk()->json('data.attendance.1.id');
        $raw = EmployeeLog::all()->toArray();
        $this->postJson('/api/employee-attendance/'.$id.'/corrections', [
            'time_in' => '2026-10-06T13:02:00+08:00', 'time_out' => '2026-10-06T17:00:00+08:00',
            'reason' => 'Verified missed timeout.', 'approved_by' => 999,
        ])->assertOk()->assertJsonPath('data.status', 'present')->assertJsonPath('data.corrections.0.approved_by', $this->admin->id);
        $this->process()->assertOk()->assertJsonPath('data.attendance.1.status', 'present');
        $this->assertSame($raw, EmployeeLog::all()->toArray());
        $this->assertDatabaseCount('attendance_corrections', 1);
        $this->postJson('/api/employee-attendance/'.$id.'/corrections', [
            'time_in' => '2026-10-06T17:00:00+08:00', 'time_out' => '2026-10-06T13:00:00+08:00', 'reason' => 'Invalid.',
        ])->assertUnprocessable();
    }

    public function test_reprocessing_is_idempotent_and_refreshes_open_cutoffs(): void
    {
        $this->taps(['08:00:00', '12:00:00', '13:00:00']);
        $this->cutoff()->assertCreated()->assertJsonPath('data.incomplete_days', 1);
        $before = EmployeeAttendance::orderBy('id')->get()->toArray();
        $this->process()->assertOk();
        $this->process()->assertOk();
        $this->assertSame($before, EmployeeAttendance::orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('employee_attendance', 2);
        $this->assertDatabaseCount('timesheets', 3);
        $this->taps(['17:00:00']);
        $this->process()->assertOk();
        $this->assertSame(0, AttendanceCutoffSummary::firstOrFail()->incomplete_days);
        $this->assertSame(28800, AttendanceCutoffSummary::firstOrFail()->credited_seconds);
    }

    public function test_confirmed_cutoffs_block_reprocessing_and_changes_until_an_authorized_reopen(): void
    {
        $this->taps(['08:00:00', '12:00:00', '13:00:00', '17:00:00']);
        $id = $this->cutoff()->assertCreated()->json('data.id');
        $this->postJson('/api/attendance-cutoffs/'.$id.'/confirm', ['confirmed_by' => 999])->assertOk()->assertJsonPath('data.confirmed_by', $this->admin->id);
        $before = AttendanceCutoffSummary::findOrFail($id)->toArray();
        $this->process()->assertUnprocessable();
        $row = EmployeeAttendance::firstOrFail();
        $this->postJson('/api/employee-attendance/'.$row->id.'/corrections', [
            'time_in' => '2026-10-06T08:00:00+08:00', 'time_out' => '2026-10-06T12:00:00+08:00', 'reason' => 'Locked.',
        ])->assertUnprocessable();
        $this->cutoff()->assertUnprocessable();
        $this->cutoff('2026-10-05', '2026-10-07')->assertUnprocessable();
        $this->postJson('/api/attendance-exceptions', [
            'employee_id' => $this->employee->employee_id, 'date' => '2026-10-06', 'type' => 'leave', 'reason' => 'Locked.',
        ])->assertUnprocessable();
        $this->taps(['18:00:00']); // Late raw taps can be retained without rewriting confirmed results.
        $this->assertSame($before, AttendanceCutoffSummary::findOrFail($id)->toArray());
        $this->postJson('/api/attendance-cutoffs/'.$id.'/reopen', ['reason' => 'Review late device sync.'])->assertOk()
            ->assertJsonPath('data.locked_at', null)->assertJsonPath('data.details.reopen_history.0.reopened_by', $this->admin->id);
        $this->process()->assertOk();
        $this->assertSame(3600, AttendanceCutoffSummary::findOrFail($id)->overtime_rendered_seconds);
        $this->postJson('/api/attendance-cutoffs/'.$id.'/confirm')->assertOk();
    }

    public function test_incomplete_and_future_cutoffs_cannot_be_confirmed(): void
    {
        $this->taps(['08:00:00']);
        $id = $this->cutoff()->assertCreated()->json('data.id');
        $this->postJson('/api/attendance-cutoffs/'.$id.'/confirm')->assertUnprocessable();
        $futureId = $this->cutoff('2026-10-21', '2026-10-21')->assertCreated()->json('data.id');
        $this->postJson('/api/attendance-cutoffs/'.$futureId.'/confirm')->assertUnprocessable();
        $this->assertSame(0, EmployeeAttendance::whereNotNull('confirmed_at')->count());
    }

    public function test_employees_can_read_only_their_own_records_and_cannot_change_attendance(): void
    {
        $own = User::factory()->forEmployee(Employee::factory()->create())->create();
        Sanctum::actingAs($own);
        foreach (['employee-logs', 'timesheets', 'employee-attendance', 'attendance-cutoffs'] as $path) {
            $this->getJson('/api/'.$path.'?employee_id='.$own->employee_id)->assertOk();
            $this->getJson('/api/'.$path.'?employee_id='.$this->employee->employee_id)->assertForbidden();
        }
        $this->process()->assertForbidden();
        $this->cutoff()->assertForbidden();
        $this->postJson('/api/employee-logs', [])->assertForbidden();
        $this->postJson('/api/attendance-exceptions', [])->assertForbidden();
    }

    public function test_read_filters_and_cutoff_validation(): void
    {
        $this->taps(['08:00:00', '12:00:00']);
        $this->process()->assertOk();
        $this->getJson('/api/employee-attendance?employee_id='.$this->employee->employee_id.'&status=absent&limit=1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/timesheets?employee_id='.$this->employee->employee_id.'&date_to=2026-10-06')->assertOk()->assertJsonCount(2, 'data');
        $this->cutoff('2026-10-07', '2026-10-06')->assertUnprocessable();
        $this->cutoff('2026-01-01', '2028-01-01')->assertUnprocessable();
    }

    public function test_factories_create_consistent_foreign_keys_and_seeders_can_be_repeated(): void
    {
        $sheet = Timesheet::factory()->create();
        $this->assertSame($sheet->employee_id, $sheet->employeeLog->employee_id);
        EmployeeAttendance::factory()->absent()->create();
        AttendanceCutoffSummary::factory()->create();
        AttendanceException::factory()->create();
        AttendanceCorrection::factory()->create();
        $this->seed(AttendanceSeeder::class);
        $this->seed([AttendanceExceptionSeeder::class, AttendanceCorrectionSeeder::class]);
        $counts = collect(['employee_logs', 'timesheets', 'employee_attendance', 'attendance_cutoff_summary'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);
        $this->seed(AttendanceSeeder::class);
        $this->seed([AttendanceExceptionSeeder::class, AttendanceCorrectionSeeder::class]);
        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->assertSame(0, DB::table('employee_logs')->where('source', 'demo')->whereNotIn('id', Timesheet::select('employee_log_id'))->count());
    }

    public function test_processing_rolls_back_all_derived_changes_when_a_reviewed_block_would_be_lost(): void
    {
        $this->process()->assertOk();
        $row = EmployeeAttendance::firstOrFail();
        AttendanceCorrection::factory()->create(['employee_attendance_id' => $row->id]);
        EmployeeSchedule::where('employee_id', $this->employee->employee_id)->update(['effective_to' => '2026-10-05']);
        $this->taps(['08:00:00']);
        $this->process()->assertUnprocessable();
        $this->assertDatabaseCount('timesheets', 0);
        $this->assertDatabaseCount('employee_attendance', 2);
        $this->assertDatabaseCount('employee_logs', 1);
    }

    public function test_unpaid_breaks_are_excluded_from_duration_totals(): void
    {
        $this->blocks([['08:00', '17:00']]);
        $this->schedule->details()->where('day_of_week', 2)->update(['unpaid_break_minutes' => 60]);
        $this->taps(['08:00:00', '17:00:00']);
        $this->cutoff()->assertCreated()->assertJsonPath('data.scheduled_seconds', 28800)
            ->assertJsonPath('data.rendered_seconds', 28800)->assertJsonPath('data.break_seconds', 3600);
    }

    public function test_attendance_routes_require_authentication(): void
    {
        auth()->forgetGuards();
        $this->getJson('/api/employee-attendance?employee_id='.$this->employee->employee_id)->assertUnauthorized();
        $this->process()->assertUnauthorized();
        $this->cutoff()->assertUnauthorized();
    }

    public function test_overlapping_rest_day_overtime_applications_form_one_expected_block(): void
    {
        foreach ([['08:00', '09:00'], ['08:30', '18:00']] as [$start, $end]) {
            OvertimeApplication::factory()->approved()->create([
                'employee_id' => $this->employee->employee_id, 'overtime_date' => '2026-10-10', 'time_from' => $start, 'time_to' => $end,
            ]);
        }
        $this->taps(['08:00:00', '18:00:00'], '2026-10-10');
        $this->cutoff('2026-10-10', '2026-10-10')->assertCreated()->assertJsonPath('data.overtime_rendered_seconds', 36000)
            ->assertJsonPath('data.overtime_approved_seconds', 36000);
        $this->assertDatabaseCount('employee_attendance', 1);
    }

    public function test_schedule_changes_reassign_taps_and_refresh_their_previous_date(): void
    {
        $this->taps(['21:55:00']);
        $this->taps(['06:00:00'], '2026-10-07');
        $id = $this->cutoff('2026-10-07', '2026-10-07')->assertCreated()->assertJsonPath('data.incomplete_days', 1)->json('data.id');
        $this->blocks([['22:00', '06:00']]);
        $this->process()->assertOk()->assertJsonPath('data.attendance.0.status', 'present');
        $this->assertSame(0, AttendanceCutoffSummary::findOrFail($id)->incomplete_days);
        $this->assertSame(1, AttendanceCutoffSummary::findOrFail($id)->absent_days);
        $this->assertSame('2026-10-06', Timesheet::where('logged_at', '2026-10-06 22:00:00')->firstOrFail()->attendance_date->toDateString());
        $this->assertDatabaseCount('employee_logs', 2);
    }

    public function test_reassigning_a_tap_from_a_locked_neighboring_date_rolls_back_processing(): void
    {
        $this->taps(['21:55:00']);
        $this->taps(['06:00:00'], '2026-10-07');
        $this->postJson('/api/attendance-exceptions', [
            'employee_id' => $this->employee->employee_id, 'date' => '2026-10-07', 'type' => 'holiday', 'reason' => 'Approved holiday.',
        ])->assertCreated();
        $id = $this->cutoff('2026-10-07', '2026-10-07')->assertCreated()->json('data.id');
        $this->postJson('/api/attendance-cutoffs/'.$id.'/confirm')->assertOk();
        $before = EmployeeAttendance::all()->toArray();
        $this->blocks([['22:00', '06:00']]);
        $this->process()->assertUnprocessable();
        $this->assertSame($before, EmployeeAttendance::all()->toArray());
        $this->assertDatabaseCount('timesheets', 1);
    }
}
