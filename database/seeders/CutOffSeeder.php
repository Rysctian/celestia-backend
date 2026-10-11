<?php

namespace Database\Seeders;

use App\Models\CutOff;
use Illuminate\Database\Seeder;

class CutOffSeeder extends Seeder
{
    public function run(): void
    {
        if (! CutOff::exists()) {
            CutOff::factory()->semiMonthly()->create();
        }

        $this->call(CutOffPeriodSeeder::class);
    }
}
