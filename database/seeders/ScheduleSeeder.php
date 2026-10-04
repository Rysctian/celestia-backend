<?php

namespace Database\Seeders;

use App\Models\Schedule;
use Illuminate\Database\Seeder;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schedule::where('name', 'Office - Mon to Fri')->exists()) {
            Schedule::factory()->withWeek()->create(['name' => 'Office - Mon to Fri']);
        }
    }
}
