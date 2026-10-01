<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Schedule;
use App\Models\User;
use App\Services\EmployeeScheduleService;
use Illuminate\Database\Seeder;

class EmployeeScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $schedules = Schedule::whereIn('name', ['Office - Mon to Fri', 'Night - Mon to Fri'])
            ->where('is_active', true)->orderBy('id')->get();
        $admin = User::whereHas('employee', fn ($query) => $query->where('type', 'admin'))->firstOrFail();
        $service = app(EmployeeScheduleService::class);

        // Use employees from this database, never fixed IDs from a previous seed.
        Employee::whereDoesntHave('schedules')->orderBy('id')->get()->each(function ($employee, $index) use ($schedules, $admin, $service) {
            if ($schedules->isEmpty()) {
                return;
            }

            $schedule = $schedules[$index % $schedules->count()];
            $service->assignSchedule([
                'employee_ids' => [$employee->employee_id],
                'schedule_id' => $schedule->id,
                'effective_from' => now($schedule->timezone)->startOfMonth()->toDateString(),
            ], $admin->id);
        });
    }
}
