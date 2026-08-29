<?php

namespace Database\Factories;

use App\Enums\RitualDaypart;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonRitualAvailabilityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'day_of_week' => fake()->numberBetween(1, 7),
            'daypart' => fake()->randomElement(RitualDaypart::cases()),
            'is_enabled' => true,
        ];
    }
}
