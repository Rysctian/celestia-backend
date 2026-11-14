<?php

namespace Database\Factories\OnlineApplication;

use App\Models\OnlineApplication\OfficialBusinessApplication;
use App\Models\OnlineApplication\OfficialBusinessApplicationDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OfficialBusinessApplicationDetail> */
class OfficialBusinessApplicationDetailFactory extends Factory
{
    protected $model = OfficialBusinessApplicationDetail::class;

    public function definition(): array
    {
        return [
            'official_business_application_id' => OfficialBusinessApplication::factory(),
            'date' => fake()->unique()->dateTimeBetween('now', '+100 days')->format('Y-m-d'),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ];
    }
}
