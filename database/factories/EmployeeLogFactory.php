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
            'logged_at' => fake()->dateTimeBetween('-1 month'),
            'device_id' => fake()->optional()->bothify('device-###'),
            'source' => 'biometric',
        ];
    }
}
