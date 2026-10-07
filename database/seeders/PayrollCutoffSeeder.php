<?php

namespace Database\Seeders;

use App\Models\PayrollCutoff;
use Illuminate\Database\Seeder;

class PayrollCutoffSeeder extends Seeder
{
    public function run(): void
    {
        if (! PayrollCutoff::exists()) {
            PayrollCutoff::factory()->withReleases()->create();
        }
    }
}
