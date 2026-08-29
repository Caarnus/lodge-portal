<?php

namespace Database\Factories;

use App\Enums\RitualProficiencyStatus;
use App\Models\Person;
use App\Models\RitualPart;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonRitualProficiencyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'ritual_part_id' => RitualPart::factory(),
            'status' => RitualProficiencyStatus::NotKnown,
            'interested_in_learning' => false,
            'willing_to_assist' => false,
            'performed_for_credit' => false,
        ];
    }
}
