<?php

namespace Database\Factories\OnlineApplication;

use App\Models\Employee;
use App\Models\OnlineApplication\OvertimeApplication;
use App\Models\OnlineApplication\OvertimeApplicationDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OvertimeApplication> */
class OvertimeApplicationFactory extends Factory
{
    protected $model = OvertimeApplication::class;

    public function definition(): array
    {
        return [
            'employee_id' => fn () => Employee::factory()->create()->employee_id,
            'reason' => 'Complete scheduled work.', 'status' => 'pending', 'submitted_at' => now(),
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(['employee_id' => $employee->employee_id]);
    }

    public function withDetails(int $count = 1): static
    {
        return $this->has(OvertimeApplicationDetail::factory()->count($count), 'details');
    }

    public function approved(): static
    {
        return $this->state(['status' => 'approved']);
    }
}
