<?php

namespace Database\Factories;

use App\Models\PayrollCutoff;
use App\Models\PayrollCutoffRelease;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PayrollCutoff> */
class PayrollCutoffFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->startOfMonth();

        return [
            'schedule_type' => 'semi-monthly',
            'quarter' => null,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->endOfMonth()->toDateString(),
            'no_dtr' => false,
            'dtr_cutoff_from' => $start->toDateString(),
            'dtr_cutoff_to' => $start->copy()->endOfMonth()->toDateString(),
            'dtr_confirmation_start' => $start->copy()->endOfMonth()->addDay()->toDateString(),
            'dtr_confirmation_end' => $start->copy()->endOfMonth()->addDays(2)->toDateString(),
            'dtr_confirmation_time_from' => '08:00:00',
            'dtr_confirmation_time_to' => '17:00:00',
            'is_active' => true,
        ];
    }

    public function withReleases(): static
    {
        return $this->afterCreating(function (PayrollCutoff $payrollCutoff) {
            PayrollCutoffRelease::factory()->create([
                'payroll_cutoff_id' => $payrollCutoff->id,
                'cutoff_no' => 1,
                'release_date' => $payrollCutoff->start_date->addDays(14),
            ]);
            PayrollCutoffRelease::factory()->create([
                'payroll_cutoff_id' => $payrollCutoff->id,
                'cutoff_no' => 2,
                'release_date' => $payrollCutoff->end_date,
            ]);
        });
    }
}
