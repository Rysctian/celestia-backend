<?php

namespace Database\Factories\OnlineApplication;

use App\Models\Employee;
use App\Models\OnlineApplication\CorrectionApplication;
use App\Models\OnlineApplication\CorrectionApplicationDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CorrectionApplication> */
class CorrectionApplicationFactory extends Factory
{
    protected $model = CorrectionApplication::class;

    public function definition(): array
    {
        return [
            'employee_id' => fn () => Employee::factory()->create()->employee_id,
            'reason' => 'Biometric scanner failed to capture time log.',
            'status' => 'pending',
            'submitted_at' => now(),
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(['employee_id' => $employee->employee_id]);
    }

    public function withDetails(int $count = 1): static
    {
        return $this->has(CorrectionApplicationDetail::factory()->count($count), 'details');
    }
}
