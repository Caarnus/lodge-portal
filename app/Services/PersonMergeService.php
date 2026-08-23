<?php

namespace App\Services;

use App\Enums\VolunteerCommitmentStatus;
use App\Models\EventVolunteerCommitment;
use App\Models\FamilyNewsletterSubscription;
use App\Models\Person;
use App\Models\PersonDirectoryPrivacySetting;
use App\Models\PersonRelationship;
use App\Models\RelationshipType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PersonMergeService
{
    public function merge(Person $source, Person $survivor): Person
    {
        if ($source->is($survivor)) {
            throw ValidationException::withMessages(['source_person_id' => 'Choose two different people.']);
        }

        return DB::transaction(function () use ($source, $survivor) {
            $source = Person::query()->lockForUpdate()->findOrFail($source->id);
            $survivor = Person::query()->lockForUpdate()->findOrFail($survivor->id);
            $sourcePrivacy = PersonDirectoryPrivacySetting::query()->where('person_id', $source->id)->lockForUpdate()->first();
            $survivorPrivacy = PersonDirectoryPrivacySetting::query()->where('person_id', $survivor->id)->lockForUpdate()->first();
            if (!$survivorPrivacy) {
                $survivorPrivacy = $survivor->directoryPrivacySetting()->create();
            }
            if ($source->user && $survivor->user) {
                throw ValidationException::withMessages(['survivor_person_id' => 'Both people have accounts. Resolve the account conflict first.']);
            }
            $overlap = $source->memberships()->whereIn('lodge_id', $survivor->memberships()->select('lodge_id'))->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['survivor_person_id' => 'Both people have a membership in the same lodge. Resolve that conflict first.']);
            }
            $hasVolunteerConflict = EventVolunteerCommitment::query()->where('person_id', $survivor->id)->where('status', VolunteerCommitmentStatus::Committed)
                ->whereExists(fn($query) => $query->selectRaw('1')->from('event_volunteer_commitments as source_commitments')->where('source_commitments.person_id', $source->id)->where('source_commitments.status', VolunteerCommitmentStatus::Committed->value)->whereColumn('source_commitments.event_volunteer_position_id', 'event_volunteer_commitments.event_volunteer_position_id')->whereColumn('source_commitments.event_occurrence_id', 'event_volunteer_commitments.event_occurrence_id'))->exists();
            if ($hasVolunteerConflict) {
                throw ValidationException::withMessages(['survivor_person_id' => 'Both people have an active commitment to the same volunteer position and occurrence. Resolve that conflict first.']);
            }
            $subscriptions = FamilyNewsletterSubscription::query()
                ->where('recipient_person_id', $source->id)
                ->orWhere('sponsoring_person_id', $source->id)
                ->lockForUpdate()
                ->get();
            $this->assertFamilySubscriptionMergeSafe($subscriptions, $source, $survivor);

            $source->memberships()->update(['person_id' => $survivor->id]);
            EventVolunteerCommitment::query()->where('person_id', $source->id)->update(['person_id' => $survivor->id]);
            foreach ($source->pastMasterTerms as $term) {
                $survivor->pastMasterTerms()->firstOrCreate(['lodge_id' => $term->lodge_id, 'year' => $term->year]);
                $term->delete();
            }
            if ($source->user && !$survivor->user) {
                $source->user->update(['person_id' => $survivor->id]);
            }
            $relationships = PersonRelationship::query()->where('person_one_id', $source->id)->orWhere('person_two_id', $source->id)->get();
            $relationshipReplacements = [];
            foreach ($relationships as $relationship) {
                $one = $relationship->person_one_id === $source->id ? $survivor->id : $relationship->person_one_id;
                $two = $relationship->person_two_id === $source->id ? $survivor->id : $relationship->person_two_id;
                $equivalent = $this->equivalent($relationship, $one, $two);
                if ($one === $two) {
                    if (FamilyNewsletterSubscription::query()->where('person_relationship_id', $relationship->id)->exists()) {
                        throw ValidationException::withMessages(['survivor_person_id' => 'A family newsletter subscription would lose its qualifying relationship. Resolve that subscription first.']);
                    }
                    $relationship->delete();
                } elseif ($equivalent) {
                    FamilyNewsletterSubscription::query()->where('person_relationship_id', $relationship->id)->update(['person_relationship_id' => $equivalent->id]);
                    $relationshipReplacements[$relationship->id] = $equivalent->id;
                    $relationship->delete();
                } else {
                    $relationship->update(['person_one_id' => $one, 'person_two_id' => $two]);
                }
            }
            foreach ($subscriptions as $subscription) {
                $subscription->update([
                    'recipient_person_id' => $subscription->recipient_person_id === $source->id ? $survivor->id : $subscription->recipient_person_id,
                    'sponsoring_person_id' => $subscription->sponsoring_person_id === $source->id ? $survivor->id : $subscription->sponsoring_person_id,
                    'person_relationship_id' => $relationshipReplacements[$subscription->person_relationship_id] ?? $subscription->person_relationship_id,
                ]);
            }
            $before = $source->toArray();
            $source->update(['email' => null, 'merged_into_person_id' => $survivor->id, 'merged_at' => now()]);
            $source->delete();
            Audit::record('person.merged', $survivor, null, $before, [
                'survivor_id' => $survivor->id,
                'source_id' => $source->id,
                'directory_privacy_resolution' => $survivorPrivacy->wasRecentlyCreated ? 'conservative_default' : 'survivor_existing',
                'source_directory_privacy_present' => $sourcePrivacy !== null,
            ]);

            return $survivor->fresh();
        });
    }

    /** @param Collection<int, FamilyNewsletterSubscription> $subscriptions */
    private function assertFamilySubscriptionMergeSafe($subscriptions, Person $source, Person $survivor): void
    {
        foreach ($subscriptions as $subscription) {
            $recipientId = $subscription->recipient_person_id === $source->id ? $survivor->id : $subscription->recipient_person_id;
            $sponsorId = $subscription->sponsoring_person_id === $source->id ? $survivor->id : $subscription->sponsoring_person_id;
            if ($recipientId === $sponsorId) {
                throw ValidationException::withMessages(['survivor_person_id' => 'A family newsletter subscription cannot name the same person as recipient and sponsor. Resolve that subscription first.']);
            }
            if ($subscription->status === 'active' && FamilyNewsletterSubscription::query()
                    ->where('lodge_id', $subscription->lodge_id)
                    ->where('recipient_person_id', $recipientId)
                    ->where('status', 'active')
                    ->whereKeyNot($subscription->id)
                    ->exists()) {
                throw ValidationException::withMessages(['survivor_person_id' => 'Both people have an active family newsletter subscription for the same lodge. Resolve that conflict first.']);
            }
        }
    }

    private function equivalent(PersonRelationship $current, int $one, int $two): ?PersonRelationship
    {
        $type = RelationshipType::findOrFail($current->relationship_type_id);
        $inverseId = RelationshipType::query()->where('key', $type->inverse_key)->value('id');

        return PersonRelationship::query()->whereKeyNot($current->id)->where(function ($query) use ($one, $two, $current, $type, $inverseId) {
            $query->where(fn($direct) => $direct->where('person_one_id', $one)->where('person_two_id', $two)->where('relationship_type_id', $current->relationship_type_id));
            if ($inverseId) {
                $query->orWhere(fn($reverse) => $reverse->where('person_one_id', $two)->where('person_two_id', $one)->where('relationship_type_id', $inverseId));
            }
            if ($type->is_symmetric) {
                $query->orWhere(fn($reverse) => $reverse->where('person_one_id', $two)->where('person_two_id', $one)->where('relationship_type_id', $current->relationship_type_id));
            }
        })->first();
    }
}
