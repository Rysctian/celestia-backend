<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeLog;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmployeeLog> */
class EmployeeLogFactory extends Factory
{
    public function definition(): array
    {
        $hasFaker = class_exists(FakerFactory::class);

        return [
            'employee_id' => fn () => Employee::factory()->create()->employee_id,
            'logged_at' => $hasFaker ? fake()->dateTimeBetween('-1 month') : now()->subDays(random_int(1, 28)),
            'device_id' => $hasFaker ? fake()->optional()->bothify('device-###') : 'device-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
            'source' => 'biometric',
        ];
    }
}
