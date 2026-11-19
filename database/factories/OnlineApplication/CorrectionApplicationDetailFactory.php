<?php

namespace Database\Factories\OnlineApplication;

use App\Models\OnlineApplication\CorrectionApplication;
use App\Models\OnlineApplication\CorrectionApplicationDetail;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CorrectionApplicationDetail> */
class CorrectionApplicationDetailFactory extends Factory
{
    protected $model = CorrectionApplicationDetail::class;

    protected static int $dayOffset = 0;

    public function definition(): array
    {
        $date = class_exists(FakerFactory::class)
            ? fake()->unique()->dateTimeBetween('now', '+100 days')->format('Y-m-d')
            : now()->addDays(self::$dayOffset++)->format('Y-m-d');

        return [
            'correction_application_id' => CorrectionApplication::factory(),
            'date' => $date,
            'am_in' => '08:00:00',
            'am_out' => '12:00:00',
            'pm_in' => '13:00:00',
            'pm_out' => '17:00:00',
        ];
    }
}
