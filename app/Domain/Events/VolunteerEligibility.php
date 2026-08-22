<?php

namespace App\Domain\Events;

use App\Enums\EventQualification;
use App\Enums\EventStatus;
use App\Enums\LodgeStatus;
use App\Models\Event;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class VolunteerEligibility
{
    public function canVolunteer(?User $user, Event $event): bool
    {
        if (! $user || ! $user->person_id || ! $user->email_verified_at || $user->approval_status !== 'approved' || $event->status !== EventStatus::Published || $event->lodge?->status !== LodgeStatus::Active) return false;
        $person = $user->person;
        if (! $person || $person->merged_into_person_id || $person->trashed()) return false;
        return $this->membership($user, $event) !== null;
    }

    private function membership(User $user, Event $event): ?Membership
    {
        $memberships = Membership::query()->with('degree')->where('person_id', $user->person_id)->where('lodge_id', $event->lodge_id)->whereNull('end_date')
            ->whereHas('status', fn (Builder $query) => $query->where('key', 'active'))->get();
        return $memberships->first(fn (Membership $membership) => $this->meetsQualification($membership, $event->required_qualification, $user)) ?: null;
    }

    private function meetsQualification(Membership $membership, ?EventQualification $required, User $user): bool
    {
        if ($required === null || $required === EventQualification::EnteredApprentice) return $membership->degree !== null;
        $rank = match ($membership->degree?->key) { 'entered_apprentice' => 1, 'fellow_craft' => 2, 'master_mason' => 3, default => 0 };
        return match ($required) {
            EventQualification::FellowCraft => $rank >= 2,
            EventQualification::MasterMason => $rank >= 3,
            EventQualification::PastMaster => $rank >= 3 && $user->person->pastMasterTerms()->exists(),
            default => false,
        };
    }
}
