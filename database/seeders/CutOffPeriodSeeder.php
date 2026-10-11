<?php

namespace Database\Seeders;

use App\Models\CutOff;
use App\Models\CutOffPeriod;
use Illuminate\Database\Seeder;

class CutOffPeriodSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CutOff::with('periods')->get() as $cutOff) {
            $numbers = $cutOff->schedule_type === 'monthly' ? [1] : [1, 2];
            $existingNumbers = $cutOff->periods->pluck('cut_off_no')->all();

            foreach ($numbers as $number) {
                if (! in_array($number, $existingNumbers, true)) {
                    CutOffPeriod::factory()->forCutOff($cutOff, $number)->create();
                }
            }
        }
    }
}
