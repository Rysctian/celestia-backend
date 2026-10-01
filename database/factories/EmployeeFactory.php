<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected static int $employeeSequence = 0;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $year = now()->year;

        self::$employeeSequence++;

        return [
            'employee_id' => sprintf('EMP-%d%04d',$year,self::$employeeSequence),
            'type' => 'employee',
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->firstName(),
            'last_name' => fake()->lastName(),
            'name_extension' => fake()->optional()->randomElement(['Jr.','Sr.','II','III',]),

            'birth_date' => fake()
                ->dateTimeBetween('-60 years', '-20 years')
                ->format('Y-m-d'),

            'birth_place' => fake()->city(),

            'gender' => fake()->randomElement([
                'Male',
                'Female',
            ]),

            'civil_status' => fake()->randomElement([
                'Single',
                'Married',
                'Widowed',
                'Separated',
            ]),

            'personal_email' => fake()->unique()->safeEmail(),
            'mobile_no' => '09' . fake()->numerify('#########'),
            'telephone_no' => fake()->optional()->phoneNumber(),
        ];
    }
}