<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\ScheduleDetail;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmployeeAttendance> */
class EmployeeAttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => fn () => Employee::factory()->create()->employee_id,
            'date' => now()->startOfWeek()->toDateString(),
            'schedule_detail_id' => ScheduleDetail::factory(),
            'schedule_id' => fn (array $attributes) => ScheduleDetail::findOrFail($attributes['schedule_detail_id'])->schedule_id,
            'sched_start' => fn (array $attributes) => $this->block($attributes)['start']->utc(),
            'sched_end' => fn (array $attributes) => $this->block($attributes)['end']->utc(),
            'time_in' => fn (array $attributes) => $attributes['sched_start'],
            'time_out' => fn (array $attributes) => $attributes['sched_end'],
            'status' => 'present', 'details' => fn (array $attributes) => [
                'timezone' => $this->block($attributes)['timezone'],
                'unpaid_break_seconds' => $this->block($attributes)['unpaid_break_minutes'] * 60,
            ],
            'computed_at' => now(),
        ];
    }

    private function block(array $attributes): array
    {
        $detail = ScheduleDetail::with('schedule.details')->findOrFail($attributes['schedule_detail_id']);
        $date = CarbonImmutable::parse($attributes['date']);

        // Factory overrides may use a different weekday from the default Monday.
        return [
            'start' => CarbonImmutable::parse($date->toDateString().' '.$detail->start_time, $detail->schedule->timezone),
            'end' => CarbonImmutable::parse($date->toDateString().' '.$detail->end_time, $detail->schedule->timezone)
                ->addDays($detail->end_time < $detail->start_time ? 1 : 0),
            'timezone' => $detail->schedule->timezone, 'unpaid_break_minutes' => $detail->unpaid_break_minutes,
        ];
    }

    public function absent(): static
    {
        return $this->state(['time_in' => null, 'time_out' => null, 'absent' => true, 'missing_time_in' => true, 'missing_time_out' => true, 'status' => 'absent']);
    }

    public function incomplete(): static
    {
        return $this->state(['time_out' => null, 'missing_time_out' => true, 'status' => 'incomplete']);
    }
}
