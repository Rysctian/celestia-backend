<?php

namespace Database\Factories\OnlineApplication;

use App\Models\Employee;
use App\Models\OnlineApplication\OfficialBusinessApplication;
use App\Models\OnlineApplication\OfficialBusinessApplicationDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OfficialBusinessApplication> */
class OfficialBusinessApplicationFactory extends Factory
{
    protected $model = OfficialBusinessApplication::class;

    public function definition(): array
    {
        return [
            'employee_id' => fn () => Employee::factory()->create()->employee_id,
            'ob_type' => 'ob',
            'destination' => 'Client Site - Makati',
            'reason' => 'On-site client meeting and deployment.',
            'status' => 'pending',
            'submitted_at' => now(),
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(['employee_id' => $employee->employee_id]);
    }

    public function wfh(): static
    {
        return $this->state(['ob_type' => 'wfh', 'destination' => null]);
    }

    public function withDetails(int $count = 1): static
    {
        return $this->has(OfficialBusinessApplicationDetail::factory()->count($count), 'details');
    }

    public function approved(): static
    {
        return $this->state(['status' => 'approved']);
    }
}
