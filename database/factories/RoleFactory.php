<?php

namespace Database\Factories;

use App\Models\Role;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Role> */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        $name = class_exists(FakerFactory::class)
            ? fake()->unique()->jobTitle()
            : 'Role '.strtoupper(Str::random(6));

        return ['code' => Str::slug($name, '_'), 'name' => $name];
    }
}
