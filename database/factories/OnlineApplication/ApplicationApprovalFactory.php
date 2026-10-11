<?php

namespace Database\Factories\OnlineApplication;

use App\Models\Employee;
use App\Models\OnlineApplication\ApplicationApproval;
use App\Models\OnlineApplication\OvertimeApplication;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/** @extends Factory<ApplicationApproval> */
class ApplicationApprovalFactory extends Factory
{
    protected $model = ApplicationApproval::class;

    public function definition(): array
    {
        return [
            'application_type' => 'overtime', 'application_id' => OvertimeApplication::factory()->withDetails(),
            'employee_id' => fn (array $attributes) => OvertimeApplication::findOrFail($attributes['application_id'])->employee_id,
            'approver_id' => fn () => Employee::factory()->create()->employee_id,
            'sequence' => 1, 'status' => 'pending', 'remarks' => null, 'acted_at' => null,
        ];
    }

    public function forApplication(Model $application): static
    {
        return $this->state([
            'employee_id' => $application->employee_id, 'application_type' => $application->getMorphClass(),
            'application_id' => $application->id,
        ]);
    }

    public function approved(): static
    {
        return $this->state(['status' => 'approved', 'acted_at' => now()]);
    }
}
