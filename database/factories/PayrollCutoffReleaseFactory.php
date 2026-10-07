<?php

namespace Database\Factories;

use App\Models\PayrollCutoff;
use App\Models\PayrollCutoffRelease;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PayrollCutoffRelease> */
class PayrollCutoffReleaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payroll_cutoff_id' => PayrollCutoff::factory(),
            'cutoff_no' => 1,
            'release_date' => now()->endOfMonth()->toDateString(),
        ];
    }
}
