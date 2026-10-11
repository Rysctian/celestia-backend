<?php

namespace Database\Factories\OnlineApplication;

use App\Models\OnlineApplication\OvertimeApplication;
use App\Models\OnlineApplication\OvertimeApplicationDetail;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OvertimeApplicationDetail> */
class OvertimeApplicationDetailFactory extends Factory
{
    protected $model = OvertimeApplicationDetail::class;

    public function definition(): array
    {
        return [
            'overtime_application_id' => OvertimeApplication::factory(),
            'date' => fake()->unique()->dateTimeBetween('now', '+100 days')->format('Y-m-d'),
            'start_time' => '18:00:00', 'end_time' => '21:00:00', 'break_seconds' => 1800,
            'requested_seconds' => fn (array $attributes) => (int) CarbonImmutable::parse($attributes['start_time'])
                ->diffInSeconds(CarbonImmutable::parse($attributes['end_time'])) - $attributes['break_seconds'],
        ];
    }
}
