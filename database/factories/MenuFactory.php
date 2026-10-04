<?php

namespace Database\Factories;

use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Menu> */
class MenuFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->words(2, true);

        return [
            'code' => Str::slug($title, '_'),
            'title' => $title,
            'path' => '/'.Str::slug($title),
            'parent_id' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function under(Menu $parent): static
    {
        return $this->state(fn () => ['parent_id' => $parent->id]);
    }
}
