<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RitualCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(3),
            'name' => fake()->unique()->words(3, true),
            'sort_order' => fake()->numberBetween(0, 1000),
            'is_active' => true,
        ];
    }
}
