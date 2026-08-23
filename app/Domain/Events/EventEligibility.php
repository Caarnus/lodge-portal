<?php

namespace App\Domain\Events;

use App\Enums\EventQualification;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class EventEligibility
{
    public function canView(?User $user, Event $event): bool
    {
        if ($event->visibility === EventVisibility::Public) {
            return true;
        }
        if (!$user?->person_id) {
            return false;
        }

        return $this->qualifyingMembership($user, $event, false) !== null;
    }

    public function canReserve(?User $user, Event $event): bool
    {
        if ($event->visibility === EventVisibility::Public) {
            return true;
        }
        if (!$user?->person_id) {
            return false;
        }

        return $this->qualifyingMembership($user, $event, true) !== null;
    }

    private function qualifyingMembership(User $user, Event $event, bool $forReservation): ?Membership
    {
        /** @var Builder<Membership> $membershipsQuery */
        $membershipsQuery = Membership::query()->with(['status', 'degree'])->where('person_id', $user->person_id)->whereNull('end_date')
            ->whereHas('status', fn(Builder $query) => $query->where('key', 'active'));
        if ($event->visibility === EventVisibility::Lodge || ($forReservation && !$event->allows_cross_lodge_reservations)) {
            $membershipsQuery->where('lodge_id', $event->lodge_id);
        }
        $memberships = $membershipsQuery->get();
        $membership = $memberships->first(fn(Membership $candidate) => $this->meetsQualification($candidate, $event->required_qualification, $user));

        return $membership instanceof Membership ? $membership : null;
    }

    private function meetsQualification(Membership $membership, ?EventQualification $required, User $user): bool
    {
        if ($required === null || $required === EventQualification::EnteredApprentice) {
            return $membership->degree !== null;
        }
        $rank = match ($membership->degree?->key) {
            'entered_apprentice' => 1,
            'fellow_craft' => 2,
            'master_mason' => 3,
            default => 0,
        };
        if ($required === EventQualification::FellowCraft) {
            return $rank >= 2;
        }
        if ($required === EventQualification::MasterMason) {
            return $rank >= 3;
        }

        return $rank >= 3 && $user->person?->pastMasterTerms()->exists();
    }
}
