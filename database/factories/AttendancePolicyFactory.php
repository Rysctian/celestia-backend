<?php

namespace Database\Factories;

use App\Models\AttendancePolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttendancePolicy> */
class AttendancePolicyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true).' Policy',
            'description' => fake()->sentence(),
            'tardy_grace_seconds' => 900,
            'tardy_count_from' => AttendancePolicy::COUNT_FROM_SCHEDULE_START,
            'absent_after_late_seconds' => null,
            'undertime_grace_seconds' => 0,
            'early_in_window_seconds' => 3600,
            'late_out_window_seconds' => 7200,
            'duplicate_tap_window_seconds' => 300,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function defaultPolicy(): static
    {
        return $this->state(fn () => [
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}
