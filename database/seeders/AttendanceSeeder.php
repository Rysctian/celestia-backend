<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([EmployeeLogSeeder::class, EmployeeAttendanceSeeder::class, AttendanceCutoffSummarySeeder::class]);
    }
}
