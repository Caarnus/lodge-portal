<?php

namespace Database\Factories;

use App\Models\Lodge;
use App\Models\MembershipStatus;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

class MembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lodge_id' => Lodge::factory(),
            'person_id' => Person::factory(),
            'membership_status_id' => fn () => MembershipStatus::query()->where('key', 'active')->value('id'),
            'primary_lodge_number' => fake()->numerify('###'),
        ];
    }
}
