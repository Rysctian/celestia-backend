<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $employees = Employee::factory(10)->create();

        foreach ($employees as $employee) {
            User::factory()
                ->forEmployee($employee)
                ->create();
        }
        // EmployeeInfo::factory(10)->create();
       User::factory()->create([
          'name' => 'Christian',
          'email' => 'admin@example.com',
          'employee_id' => $employees->first()->employee_id,
          'password' => 'a',
      ]);
    }
}