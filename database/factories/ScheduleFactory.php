<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\ScheduleDetail;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Schedule> */
class ScheduleFactory extends Factory
{
    public function definition(): array
    {
        $name = class_exists(FakerFactory::class)
            ? fake()->words(3, true)
            : 'Schedule '.strtoupper(Str::random(6));

        return ['name' => $name, 'timezone' => 'Asia/Manila', 'is_active' => true];
    }

    public function withWeek(): static
    {
        return $this->afterCreating(function (Schedule $schedule) {
            foreach (range(1, 7) as $weekday) {
                $rest = $weekday >= 6;
                ScheduleDetail::factory()->create([
                    'schedule_id' => $schedule->id,
                    'day_of_week' => $weekday,
                    'is_rest_day' => $rest,
                    'start_time' => $rest ? null : '08:00',
                    'end_time' => $rest ? null : '12:00',
                ]);
                if (! $rest) {
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
