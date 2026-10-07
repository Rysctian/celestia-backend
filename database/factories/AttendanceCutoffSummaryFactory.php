<?php

namespace Database\Factories;

use App\Models\AttendanceCutoffSummary;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttendanceCutoffSummary> */
class AttendanceCutoffSummaryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => fn () => Employee::factory()->create()->employee_id,
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->startOfMonth()->addDays(14)->toDateString(),
            'computed_at' => now(), 'details' => ['attendance_ids' => []],
        ];
    }
}
