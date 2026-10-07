<?php

namespace Database\Factories;

use App\Models\EmployeeAttendance;
use App\Models\EmployeeAttendanceLog;
use App\Models\EmployeeLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmployeeAttendanceLog> */
class EmployeeAttendanceLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_attendance_id' => EmployeeAttendance::factory(),
            'employee_log_id' => fn (array $attributes) => EmployeeLog::factory()->create([
                'employee_id' => EmployeeAttendance::findOrFail($attributes['employee_attendance_id'])->employee_id,
            ])->id,
            'sequence' => 1,
            'log_type' => 'in',
        ];
    }
}
