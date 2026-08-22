<?php

namespace Database\Factories;

use App\Models\Membership;
use Illuminate\Database\Eloquent\Factories\Factory;

class MembershipCommunicationPreferenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'membership_id' => fn () => Membership::withoutEvents(fn () => Membership::factory()->create())->id,
            'lodge_id' => fn (array $attributes) => Membership::query()->findOrFail($attributes['membership_id'])->lodge_id,
            'receives_lodge_email' => true,
            'receives_print_newsletter' => false,
        ];
    }
}
