<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeLog;
use App\Models\EmployeeSchedule;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeAttendanceResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_response_flattens_slots_across_dates_and_excludes_internal_data(): void
    {
        $employee = Employee::factory()->create();
        Sanctum::actingAs(User::factory()->forEmployee($employee)->create([
            'role_id' => Role::where('code', 'admin')->firstOrFail()->id,
        ]));
        $schedule = Schedule::factory()->withWeek()->create();
        EmployeeSchedule::factory()->create([
            'employee_id' => $employee->employee_id,
            'schedule_id' => $schedule->id,
            'effective_from' => '2026-10-01',
        ]);
        foreach (['08:00:00', '17:00:00'] as $time) {
            EmployeeLog::factory()->create([
                'employee_id' => $employee->employee_id,
                'logged_at' => '2026-10-12 '.$time,
            ]);
        }

        $response = $this->getJson('/api/employee-attendance?'.http_build_query([
            'employee_id' => $employee->employee_id,
            'from' => '2026-10-12',
            'to' => '2026-10-13',
        ]))->assertOk()
            ->assertJsonPath('date-from', '2026-10-12')
            ->assertJsonPath('date-to', '2026-10-13')
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.sched_start', '08:00:00')
            ->assertJsonPath('data.1.sched_start', '13:00:00')
            ->assertJsonPath('data.0.rendered_seconds', 14400)
            ->assertJsonPath('data.1.rendered_seconds', 14400)
            ->assertJsonPath('data.2.date', '2026-10-13')
            ->assertJsonPath('data.2.absent', true)
            ->assertJsonPath('data.2.undertime_seconds', 14400);

        $this->assertSame([
            'date', 'sched_start', 'sched_end', 'time_in', 'time_out',
            'scheduled_seconds', 'rendered_seconds', 'tardy_seconds',
            'undertime_seconds', 'early_out_seconds', 'absent', 'remarks',
        ], array_keys($response->json('data.0')));
    }
}
