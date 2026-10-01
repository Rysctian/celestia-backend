<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmployeeSchedule> */
class EmployeeScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => fn () => Employee::factory()->create()->employee_id,
            'schedule_id' => Schedule::factory()->withWeek(),
            'effective_from' => now('Asia/Manila')->startOfMonth()->toDateString(),
            'effective_to' => null,
            'assigned_by' => null,
        ];
    }
}
