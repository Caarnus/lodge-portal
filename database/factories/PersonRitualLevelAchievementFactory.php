<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\RitualProgramLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonRitualLevelAchievementFactory extends Factory
{
    public function definition(): array
    {
        $threshold = fake()->numberBetween(1, 2000);

        return [
            'person_id' => Person::factory(),
            'ritual_program_level_id' => RitualProgramLevel::factory(),
            'achieved_at' => now(),
            'point_total_at_achievement' => $threshold,
            'level_name_snapshot' => fake()->words(2, true),
            'threshold_snapshot' => $threshold,
        ];
    }
}
