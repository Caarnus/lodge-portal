<?php

namespace Database\Factories;

use App\Enums\RitualVisibilityScope;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonRitualSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'person_id' => fn () => Person::withoutEvents(fn () => Person::factory()->create())->id,
            'visibility_scope' => RitualVisibilityScope::Hidden,
        ];
    }
}
