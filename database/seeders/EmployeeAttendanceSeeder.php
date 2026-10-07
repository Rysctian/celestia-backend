<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\Schedule;
use Illuminate\Database\Seeder;

class EmployeeAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $schedule = Schedule::first();

        foreach (Employee::all() as $employee) {
            EmployeeAttendance::firstOrCreate([
                'employee_id' => $employee->employee_id,
                'date' => now()->toDateString(),
            ], [
                'schedule_id' => $schedule?->id,
                'sched_start' => '08:00:00',
                'sched_end' => '17:00:00',
                'time_in' => '08:00:00',
                'time_out' => '17:00:00',
                'scheduled_seconds' => 32400,
                'rendered_seconds' => 32400,
                'status' => 'present',
            ]);
        }
    }
}
