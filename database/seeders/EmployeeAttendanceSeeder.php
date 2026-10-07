<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EmployeeAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        // The processor builds both interpreted taps and per-block attendance together.
        $this->call(TimesheetSeeder::class);
    }
}
