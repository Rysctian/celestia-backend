<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmployeeLog> */
class EmployeeLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => fn () => Employee::factory()->create()->employee_id,
            'logged_at' => now()->startOfDay()->addHours(8),
            'source' => 'biometric', 'device_id' => 'test-device',
            'external_log_id' => fake()->uuid(), 'raw_payload' => ['device' => 'test-device'],
        ];
    }
}
