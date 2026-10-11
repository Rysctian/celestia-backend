<?php

namespace Database\Factories;

use App\Models\CutOff;
use App\Models\CutOffPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CutOff> */
class CutOffFactory extends Factory
{
    public function definition(): array
    {
        return [
            'schedule_type' => 'monthly',
            'name' => now()->format('F Y'),
            'year' => now()->year,
            'month' => now()->month,
            'is_active' => true,
        ];
    }

    public function semiMonthly(): static
    {
        return $this->state(['schedule_type' => 'semi_monthly']);
    }

    public function withPeriods(): static
    {
        return $this->afterCreating(function (CutOff $cutOff) {
            $numbers = $cutOff->schedule_type === 'monthly' ? [1] : [1, 2];
            foreach ($numbers as $number) {
                CutOffPeriod::factory()->forCutOff($cutOff, $number)->create();
            }
        });
    }
}
