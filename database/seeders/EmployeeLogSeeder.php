<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeLog;
use Illuminate\Database\Seeder;

class EmployeeLogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Employee::all() as $employee) {
            foreach ([8, 17] as $hour) {
                EmployeeLog::firstOrCreate([
                    'employee_id' => $employee->employee_id,
                    'logged_at' => now()->startOfDay()->setHour($hour),
                ], [
                    'device_id' => 'demo-device',
                    'source' => 'demo',
                ]);
            }
        }
    }
}
