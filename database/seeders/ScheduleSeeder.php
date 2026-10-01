<?php

namespace Database\Seeders;

use App\Models\Schedule;
use Illuminate\Database\Seeder;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Office - Mon to Fri' => false, 'Night - Mon to Fri' => true] as $name => $night) {
            if (! Schedule::where('name', $name)->exists()) {
                Schedule::factory()->withWeek($night)->create(['name' => $name]);
            }
        }
    }
}
