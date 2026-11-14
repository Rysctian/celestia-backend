<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Faker\Factory as FakerFactory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        if (class_exists(FakerFactory::class)) {
            $faker = FakerFactory::create();
            $name = $faker->name();
            $email = $faker->unique()->safeEmail();
        } else {
            $suffix = strtolower(Str::random(8));
            $name = 'User ' . strtoupper($suffix);
            $email = "user.{$suffix}@example.com";
        }

        return [
            'name' => $name,
            'email' => $email,
            'employee_id' => null,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn () => [
            'employee_id' => $employee->employee_id,
            'name' => $employee->first_name . ' ' . $employee->last_name,
        ]);
    }

    public function withRole(Role $role): static
    {
        return $this->state(fn () => [
            'role_id' => $role->id,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
        ]);
    }
}
