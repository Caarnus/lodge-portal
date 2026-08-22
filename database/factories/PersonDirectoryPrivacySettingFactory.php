<?php

namespace Database\Factories;

use App\Enums\DirectoryVisibilityScope;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonDirectoryPrivacySettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'person_id' => fn () => Person::withoutEvents(fn () => Person::factory()->create())->id,
            'scope' => DirectoryVisibilityScope::OwnLodge,
            'show_email' => false,
            'show_phone' => false,
            'show_address' => false,
            'show_profile_photo' => false,
            'show_degree' => false,
        ];
    }
}
