<?php

namespace Database\Seeders;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        EmployeeFactory::factory()->count(10)->create();
    }
}