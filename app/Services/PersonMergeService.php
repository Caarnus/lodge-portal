<?php

namespace App\Services;

use App\Enums\VolunteerCommitmentStatus;
use App\Models\EventVolunteerCommitment;
use App\Models\Person;
use App\Models\PersonDirectoryPrivacySetting;
use App\Models\PersonRelationship;
use App\Models\RelationshipType;
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
            if (! $survivorPrivacy) {
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
                ->whereExists(fn ($query) => $query->selectRaw('1')->from('event_volunteer_commitments as source_commitments')->where('source_commitments.person_id', $source->id)->where('source_commitments.status', VolunteerCommitmentStatus::Committed->value)->whereColumn('source_commitments.event_volunteer_position_id', 'event_volunteer_commitments.event_volunteer_position_id')->whereColumn('source_commitments.event_occurrence_id', 'event_volunteer_commitments.event_occurrence_id'))->exists();
            if ($hasVolunteerConflict) {
                throw ValidationException::withMessages(['survivor_person_id' => 'Both people have an active commitment to the same volunteer position and occurrence. Resolve that conflict first.']);
            }

            $source->memberships()->update(['person_id' => $survivor->id]);
            EventVolunteerCommitment::query()->where('person_id', $source->id)->update(['person_id' => $survivor->id]);
            foreach ($source->pastMasterTerms as $term) {
                $survivor->pastMasterTerms()->firstOrCreate(['lodge_id' => $term->lodge_id, 'year' => $term->year]);
                $term->delete();
            }
            if ($source->user && ! $survivor->user) {
                $source->user->update(['person_id' => $survivor->id]);
            }
            $relationships = PersonRelationship::query()->where('person_one_id', $source->id)->orWhere('person_two_id', $source->id)->get();
            foreach ($relationships as $relationship) {
                $one = $relationship->person_one_id === $source->id ? $survivor->id : $relationship->person_one_id;
                $two = $relationship->person_two_id === $source->id ? $survivor->id : $relationship->person_two_id;
                if ($one === $two || $this->equivalentExists($relationship, $one, $two)) {
                    $relationship->delete();
                } else {
                    $relationship->update(['person_one_id' => $one, 'person_two_id' => $two]);
                }
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

    private function equivalentExists(PersonRelationship $current, int $one, int $two): bool
    {
        $type = RelationshipType::findOrFail($current->relationship_type_id);
        $inverseId = RelationshipType::query()->where('key', $type->inverse_key)->value('id');

        return PersonRelationship::query()->whereKeyNot($current->id)->where(function ($query) use ($one, $two, $current, $type, $inverseId) {
            $query->where(fn ($direct) => $direct->where('person_one_id', $one)->where('person_two_id', $two)->where('relationship_type_id', $current->relationship_type_id));
            if ($inverseId) {
                $query->orWhere(fn ($reverse) => $reverse->where('person_one_id', $two)->where('person_two_id', $one)->where('relationship_type_id', $inverseId));
            }
            if ($type->is_symmetric) {
                $query->orWhere(fn ($reverse) => $reverse->where('person_one_id', $two)->where('person_two_id', $one)->where('relationship_type_id', $current->relationship_type_id));
            }
        })->exists();
    }
}
