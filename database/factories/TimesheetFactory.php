<?php

namespace Database\Factories;

use App\Models\EmployeeLog;
use App\Models\Timesheet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Timesheet> */
class TimesheetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_log_id' => EmployeeLog::factory(),
            'employee_id' => fn (array $attributes) => EmployeeLog::findOrFail($attributes['employee_log_id'])->employee_id,
            'attendance_date' => fn (array $attributes) => EmployeeLog::findOrFail($attributes['employee_log_id'])->logged_at->setTimezone(config('attendance.timezone'))->toDateString(),
            'logged_at' => fn (array $attributes) => EmployeeLog::findOrFail($attributes['employee_log_id'])->logged_at,
            'schedule_id' => null, 'schedule_detail_id' => null, 'log_type' => null,
            'status' => 'pending', 'reason' => null, 'processed_at' => null,
        ];
    }
}
