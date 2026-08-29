<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RitualProgramLevelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(3),
            'name' => fake()->unique()->words(2, true),
            'point_threshold' => fake()->numberBetween(1, 2000),
            'sort_order' => fake()->numberBetween(0, 1000),
            'is_active' => true,
        ];
    }
}
