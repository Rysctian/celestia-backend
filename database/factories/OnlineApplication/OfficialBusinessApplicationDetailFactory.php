<?php

namespace Database\Factories\OnlineApplication;

use App\Models\OnlineApplication\OfficialBusinessApplication;
use App\Models\OnlineApplication\OfficialBusinessApplicationDetail;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OfficialBusinessApplicationDetail> */
class OfficialBusinessApplicationDetailFactory extends Factory
{
    protected $model = OfficialBusinessApplicationDetail::class;

    protected static int $dayOffset = 0;

    public function definition(): array
    {
        $date = class_exists(FakerFactory::class)
            ? fake()->unique()->dateTimeBetween('now', '+100 days')->format('Y-m-d')
            : now()->addDays(self::$dayOffset++)->format('Y-m-d');

        return [
            'official_business_application_id' => OfficialBusinessApplication::factory(),
            'date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ];
    }
}
