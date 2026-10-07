<?php

namespace Database\Factories;

use App\Models\AttendanceException;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttendanceException> */
class AttendanceExceptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => fn () => Employee::factory()->create()->employee_id,
            'date' => now()->toDateString(), 'type' => 'leave', 'reason' => 'Approved attendance exception.',
            'approved_by' => User::factory()->state(['employee_id' => fn () => Employee::factory()->create()->employee_id]), 'approved_at' => now(),
        ];
    }
}
