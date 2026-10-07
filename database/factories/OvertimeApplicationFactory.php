<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\OvertimeApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OvertimeApplication>
 */
class OvertimeApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => fn () => Employee::factory()->create()->employee_id,
            'overtime_date' => now()->toDateString(), 'time_from' => '17:00:00', 'time_to' => '18:00:00',
            'reason' => 'Complete scheduled work.', 'allow_approver' => true, 'status' => 'pending',
            'approved_at' => null, 'rejection_reason' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => 'approved', 'approved_at' => now()]);
    }
}
