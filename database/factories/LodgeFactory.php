<?php

namespace Database\Factories;

use App\Models\Lodge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lodge>
 */
class LodgeFactory extends Factory
{
    protected $model = Lodge::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' Lodge', 'number' => (string) fake()->unique()->numberBetween(1, 999),
            'slug' => fake()->unique()->slug(),
            'city' => fake()->city(), 'state' => 'IN',
            'jurisdiction' => 'Indiana',
            'physical_address' => fake()->streetAddress(),
            'timezone' => 'America/Chicago',
            'public_email' => fake()->unique()->safeEmail(),
            'status' => 'active',
            'primary_color' => '#1E3A5F',
            'secondary_color' => '#D4AF37',
        ];
    }
}
