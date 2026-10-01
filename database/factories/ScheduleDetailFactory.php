<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\ScheduleDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ScheduleDetail> */
class ScheduleDetailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'schedule_id' => Schedule::factory(),
            'day_of_week' => 1,
            'is_rest_day' => false,
            'start_time' => '08:00',
            'end_time' => '12:00',
            'ends_next_day' => false,
        ];
    }
}
