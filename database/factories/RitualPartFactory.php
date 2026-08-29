<?php

namespace Database\Factories;

use App\Models\RitualCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class RitualPartFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ritual_category_id' => RitualCategory::factory(),
            'key' => fake()->unique()->slug(4),
            'name' => fake()->unique()->words(4, true),
            'sort_order' => fake()->numberBetween(0, 1000),
            'counts_toward_program' => true,
            'point_value' => fake()->numberBetween(1, 250),
            'is_active' => true,
        ];
    }

    public function nonPoint(): static
    {
        return $this->state(fn () => [
            'counts_toward_program' => false,
            'point_value' => null,
        ]);
    }
}
