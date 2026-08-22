<?php

namespace Database\Factories;

use App\Models\FamilyNewsletterSubscription;
use App\Models\Lodge;
use App\Models\Person;
use App\Models\PersonRelationship;
use App\Models\RelationshipType;
use Illuminate\Database\Eloquent\Factories\Factory;

class FamilyNewsletterSubscriptionFactory extends Factory
{
    protected $model = FamilyNewsletterSubscription::class;

    public function definition(): array
    {
        $lodge = Lodge::factory()->create();
        $recipient = Person::factory()->create();
        $sponsor = Person::factory()->create();
        $type = RelationshipType::query()->firstOrCreate(
            ['key' => 'spouse'],
            ['name' => 'Spouse', 'inverse_key' => 'spouse', 'inverse_name' => 'Spouse', 'is_symmetric' => true],
        );
        $relationship = PersonRelationship::create([
            'owning_lodge_id' => $lodge->id,
            'person_one_id' => $recipient->id,
            'person_two_id' => $sponsor->id,
            'relationship_type_id' => $type->id,
        ]);

        return [
            'lodge_id' => $lodge->id,
            'recipient_person_id' => $recipient->id,
            'sponsoring_person_id' => $sponsor->id,
            'person_relationship_id' => $relationship->id,
            'receives_email' => true,
            'receives_print' => false,
            'status' => 'active',
            'consent_source' => 'test',
            'requested_at' => now(),
        ];
    }
}
