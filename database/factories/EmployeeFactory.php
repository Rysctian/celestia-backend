<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Faker\Factory as Faker;
/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected static int $employeeSequence = 0;

    public function definition(): array
    {
        $year = now()->year;

        if (self::$employeeSequence === 0) {
            $prefix = sprintf('EMP-%d', $year);
            $latestId = Employee::where('employee_id', 'like', $prefix.'%')
                ->orderByDesc('employee_id')
                ->value('employee_id');

            if ($latestId) {
                self::$employeeSequence = (int) substr($latestId, strlen($prefix));
            }
        }

        self::$employeeSequence++;
        $employeeId = sprintf('EMP-%d%04d', $year, self::$employeeSequence);

        if (class_exists(Faker::class)) {
            $faker = Faker::create();

            return [
                'employee_id' => $employeeId,
                'type' => 'employee',
                'first_name' => $faker->firstName(),
                'middle_name' => $faker->optional()->firstName(),
                'last_name' => $faker->lastName(),
                'name_extension' => $faker->optional()->randomElement(['Jr.', 'Sr.', 'II', 'III']),

                'birth_date' => $faker->dateTimeBetween('-60 years', '-20 years')->format('Y-m-d'),
                'birth_place' => $faker->city(),

                'gender' => $faker->randomElement(['Male', 'Female']),
                'civil_status' => $faker->randomElement([
                    'Single',
                    'Married',
                    'Widowed',
                    'Separated',
                ]),

                'personal_email' => $faker->unique()->safeEmail(),
                'mobile_no' => '09' . $faker->numerify('#########'),
                'telephone_no' => $faker->optional()->phoneNumber(),
            ];
        }

        $firstNames = ['Juan', 'Maria', 'Jose', 'Ana', 'Carlo', 'Sofia', 'Miguel', 'Elena', 'Rafael', 'Camille'];
        $lastNames = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Mendoza', 'Torres', 'Flores', 'Rivera', 'Ramos'];
        $cities = ['Manila', 'Quezon City', 'Makati', 'Cebu City', 'Davao City'];
        $genders = ['Male', 'Female'];
        $civilStatuses = ['Single', 'Married', 'Widowed', 'Separated'];

        $firstName = $firstNames[array_rand($firstNames)];
        $lastName = $lastNames[array_rand($lastNames)];

        return [
            'employee_id' => $employeeId,
            'type' => 'employee',
            'first_name' => $firstName,
            'middle_name' => null,
            'last_name' => $lastName,
            'name_extension' => null,

            'birth_date' => now()->subYears(random_int(20, 60))->subDays(random_int(0, 364))->format('Y-m-d'),
            'birth_place' => $cities[array_rand($cities)],

            'gender' => $genders[array_rand($genders)],
            'civil_status' => $civilStatuses[array_rand($civilStatuses)],

            'personal_email' => strtolower($firstName.'.'.$lastName.'.'.self::$employeeSequence.'@example.com'),
            'mobile_no' => '09'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'telephone_no' => null,
        ];
    }
}