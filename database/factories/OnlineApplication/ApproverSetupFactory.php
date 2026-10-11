<?php

namespace Database\Factories\OnlineApplication;

use App\Models\Employee;
use App\Models\OnlineApplication\ApproverSetup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApproverSetup> */
class ApproverSetupFactory extends Factory
{
    protected $model = ApproverSetup::class;

    public function definition(): array
    {
        return [
            'employee_id' => fn () => Employee::factory()->create()->employee_id,
            'application_type' => 'overtime',
            'approver_id' => fn () => Employee::factory()->create()->employee_id,
            'sequence' => 1,
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(['employee_id' => $employee->employee_id]);
    }
}
