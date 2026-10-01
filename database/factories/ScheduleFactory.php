<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\ScheduleDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Schedule> */
class ScheduleFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->words(3, true), 'timezone' => 'Asia/Manila', 'is_active' => true];
    }

    public function withWeek(bool $night = false): static
    {
        return $this->afterCreating(function (Schedule $schedule) use ($night) {
            foreach (range(1, 7) as $weekday) {
                $rest = $weekday >= 6;
                ScheduleDetail::factory()->create([
                    'schedule_id' => $schedule->id,
                    'day_of_week' => $weekday,
                    'is_rest_day' => $rest,
                    'start_time' => $rest ? null : ($night ? '22:00' : '08:00'),
                    'end_time' => $rest ? null : ($night ? '07:00' : '12:00'),
                    'ends_next_day' => ! $rest && $night,
                ]);
                if (! $rest && ! $night) {
                    ScheduleDetail::factory()->create([
                        'schedule_id' => $schedule->id,
                        'day_of_week' => $weekday,
                        'start_time' => '13:00',
                        'end_time' => '17:00',
                    ]);
                }
            }
        });
    }
}
