<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::factory()->count(10)->create();

        foreach ($employees as $employee) {
            User::factory()
                ->forEmployee($employee)
                ->create();
        }
    }
}