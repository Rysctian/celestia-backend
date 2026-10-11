<?php

namespace Database\Seeders\OnlineApplication;

use Illuminate\Database\Seeder;

class OvertimeApplicationSeeder extends Seeder
{
    public function run(): void
    {
        // Parent, details and the approval snapshot are one submitted operation.
        $this->call(OvertimeApplicationDetailSeeder::class);
    }
}
