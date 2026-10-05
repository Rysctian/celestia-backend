<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MenuAccessSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MenuSeeder::class,
            AccessSeeder::class,
        ]);
    }
}
