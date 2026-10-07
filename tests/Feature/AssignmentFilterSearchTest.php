<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssignmentFilterSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_list_filters_sorts_and_pages_only_the_selected_employee(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 6)->startOfDay());
        $employee = Employee::factory()->create();
        $user = User::factory()->forEmployee($employee)->withRole(Role::where('code', 'admin')->firstOrFail())->create();
        Sanctum::actingAs($user);
        $office = Schedule::factory()->create(['name' => 'Office']);
        $night = Schedule::factory()->create(['name' => 'Night']);
        $dates = ['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04', '2026-10-01', '2026-10-10', '2026-10-11', '2026-10-12'];
        $assignments = collect($dates)->map(fn ($date, $index) => EmployeeSchedule::create([
            'employee_id' => $employee->employee_id,
            'schedule_id' => $index % 2 === 0 ? $office->id : $night->id,
            'effective_from' => $date,
            'effective_to' => $index === 4 ? null : $date,
            'assigned_by' => $user->id,
        ]));
        EmployeeSchedule::create([
            'employee_id' => Employee::factory()->create()->employee_id,
            'schedule_id' => $office->id,
            'effective_from' => '2026-10-01',
        ]);
        $endpoint = "/api/employees/{$employee->employee_id}/schedules";
        $response = $this->getJson($endpoint.'?limit=3&offset=3&sort_by=effective_from&sort_order=asc')
            ->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame($assignments->slice(3, 3)->pluck('id')->values()->all(), array_column($response->json('data'), 'id'));
        $this->getJson($endpoint.'?search=Office&sort_by=id&sort_order=desc&limit=2')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $assignments[6]->id);
        $this->getJson($endpoint.'?is_current=true')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assignments[4]->id);
        $this->getJson($endpoint.'?is_current=0')->assertOk()->assertJsonCount(7, 'data');
        $this->getJson($endpoint.'?date_from=2026-09-02&date_to=2026-09-03')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson($endpoint.'?schedule_id='.$night->id.'&search=Office')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($endpoint.'?offset=100')->assertOk()->assertJsonCount(0, 'data');
    }
}
