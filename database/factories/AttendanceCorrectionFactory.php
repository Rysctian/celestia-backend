<?php

namespace Database\Factories;

use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttendanceCorrection> */
class AttendanceCorrectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_attendance_id' => EmployeeAttendance::factory(),
            'time_in' => fn (array $attributes) => EmployeeAttendance::findOrFail($attributes['employee_attendance_id'])->sched_start,
            'time_out' => fn (array $attributes) => EmployeeAttendance::findOrFail($attributes['employee_attendance_id'])->sched_end,
            'reason' => 'HR verified the missing tap.',
            'approved_by' => User::factory()->state(['employee_id' => fn () => Employee::factory()->create()->employee_id]), 'approved_at' => now(),
        ];
    }
}
