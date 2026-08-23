<?php

namespace App\Services;

use App\Models\Lodge;
use App\Models\Membership;
use App\Models\Person;
use App\Models\PersonRelationship;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PersonAccess
{
    private const LODGE_REACHABLE_STATUS_KEYS = ['active', 'petitioner'];

    public function visibleQuery(Lodge $lodge): Builder
    {
        return Person::query()->where(function (Builder $query) use ($lodge) {
            $query->whereHas('memberships', fn(Builder $memberships) => $this->activeMembershipQuery($memberships, $lodge))
                ->orWhereHas('relationshipsFrom.personTwo.memberships', fn(Builder $memberships) => $this->activeMembershipQuery($memberships, $lodge))
                ->orWhereHas('relationshipsTo.personOne.memberships', fn(Builder $memberships) => $this->activeMembershipQuery($memberships, $lodge));
        });
    }

    public function canView(User $user, Lodge $lodge, Person $person): bool
    {
        return $user->is_platform_admin || ($user->hasLodgePermission($lodge, 'people.view')
                && $this->visibleQuery($lodge)->whereKey($person->id)->exists());
    }

    public function canManagePerson(User $user, Lodge $lodge, Person $person): bool
    {
        if ($user->is_platform_admin) {
            return true;
        }

        if (!$user->hasLodgePermission($lodge, 'people.manage')) {
            return false;
        }

        if ($this->hasActiveMembership($lodge, $person)) {
            return true;
        }

        return PersonRelationship::query()
            ->where(fn(Builder $query) => $query->where('person_one_id', $person->id)->orWhere('person_two_id', $person->id))
            ->get()->contains(fn(PersonRelationship $relationship) => $this->canManageRelationship($user, $lodge, $relationship));
    }

    public function manageablePersonIds(User $user, Lodge $lodge, array $candidateIds): Collection
    {
        $candidateIds = collect($candidateIds);
        if ($user->is_platform_admin) {
            return $candidateIds;
        }
        if (!$user->hasLodgePermission($lodge, 'people.manage')) {
            return collect();
        }

        $directIds = Membership::query()->whereIn('person_id', $candidateIds)->where('lodge_id', $lodge->id)
            ->whereNull('end_date')->whereHas('status', fn(Builder $query) => $query->whereIn('key', self::LODGE_REACHABLE_STATUS_KEYS))->pluck('person_id');
        if (!$user->hasLodgePermission($lodge, 'relationships.manage')) {
            return $directIds;
        }

        $primaryIds = Membership::query()->where('lodge_id', $lodge->id)->where('primary_lodge_number', $lodge->number)
            ->whereNull('end_date')->whereHas('status', fn(Builder $query) => $query->whereIn('key', self::LODGE_REACHABLE_STATUS_KEYS))->pluck('person_id');
        $relatedIds = PersonRelationship::query()
            ->where(fn(Builder $query) => $query->whereIn('person_one_id', $candidateIds)->orWhereIn('person_two_id', $candidateIds))
            ->where(fn(Builder $query) => $query->whereIn('person_one_id', $primaryIds)->orWhereIn('person_two_id', $primaryIds))
            ->get(['person_one_id', 'person_two_id'])->flatMap(fn(PersonRelationship $relationship) => [
                $relationship->person_one_id,
                $relationship->person_two_id,
            ]);

        return $directIds->merge($relatedIds)->intersect($candidateIds)->unique()->values();
    }

    public function canViewRelationship(User $user, Lodge $lodge, PersonRelationship $relationship): bool
    {
        if ($user->is_platform_admin) {
            return true;
        }

        return $user->hasLodgePermission($lodge, 'relationships.view')
            && Membership::query()->where('lodge_id', $lodge->id)
                ->whereIn('person_id', [$relationship->person_one_id, $relationship->person_two_id])
                ->whereNull('end_date')->whereHas('status', fn(Builder $query) => $query->whereIn('key', self::LODGE_REACHABLE_STATUS_KEYS))->exists();
    }

    public function canManageRelationship(User $user, Lodge $lodge, PersonRelationship $relationship): bool
    {
        if ($user->is_platform_admin) {
            return true;
        }

        return $user->hasLodgePermission($lodge, 'relationships.manage')
            && Membership::query()->where('lodge_id', $lodge->id)
                ->whereIn('person_id', [$relationship->person_one_id, $relationship->person_two_id])
                ->where('primary_lodge_number', $lodge->number)->whereNull('end_date')
                ->whereHas('status', fn(Builder $query) => $query->whereIn('key', self::LODGE_REACHABLE_STATUS_KEYS))->exists();
    }

    public function hasActiveMembership(Lodge $lodge, Person $person): bool
    {
        return Membership::query()->where('lodge_id', $lodge->id)->where('person_id', $person->id)
            ->whereNull('end_date')->whereHas('status', fn(Builder $query) => $query->whereIn('key', self::LODGE_REACHABLE_STATUS_KEYS))->exists();
    }

    private function activeMembershipQuery(Builder $query, Lodge $lodge): Builder
    {
        return $query->where('lodge_id', $lodge->id)->whereNull('end_date')
            ->whereHas('status', fn(Builder $statuses) => $statuses->whereIn('key', self::LODGE_REACHABLE_STATUS_KEYS));
    }
}
