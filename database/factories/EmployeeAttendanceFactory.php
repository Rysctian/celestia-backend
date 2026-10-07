<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmployeeAttendance> */
class EmployeeAttendanceFactory extends Factory
{
    public function definition(): array
    {
        $date = now()->startOfDay();
        $schedStart = $date->copy()->setTime(8, 0);
        $schedEnd = $date->copy()->setTime(17, 0);

        return [
            'employee_id' => fn () => Employee::factory()->create()->employee_id,
            'date' => $date->toDateString(),
            'schedule_id' => Schedule::factory(),
            'sched_start' => $schedStart->format('H:i:s'),
            'sched_end' => $schedEnd->format('H:i:s'),
            'time_in' => $schedStart->format('H:i:s'),
            'time_out' => $schedEnd->format('H:i:s'),
            'scheduled_seconds' => 32400,
            'rendered_seconds' => 32400,
            'absent' => false,
            'tardy_seconds' => 0,
            'undertime' => false,
            'undertime_seconds' => 0,
            'early_dismiss' => false,
            'holiday' => false,
            'suspended' => false,
            'remarks' => null,
            'status' => 'present',
            'details' => null,
            'confirmed_at' => null,
        ];
    }

    public function absent(): static
    {
        return $this->state(fn () => [
            'time_in' => null,
            'time_out' => null,
            'rendered_seconds' => 0,
            'absent' => true,
            'status' => 'absent',
        ]);
    }
}
