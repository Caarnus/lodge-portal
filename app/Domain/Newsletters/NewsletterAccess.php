<?php

namespace App\Domain\Newsletters;

use App\Enums\LodgeStatus;
use App\Models\Lodge;
use App\Models\Membership;
use App\Models\User;

class NewsletterAccess
{
    public function canRead(User $user, Lodge $lodge): bool
    {
        if ($lodge->status !== LodgeStatus::Active || $user->approval_status !== 'approved' || ! $user->hasVerifiedEmail()) {
            return false;
        }

        $person = $user->person;
        if (! $person || $person->trashed() || $person->merged_at || $person->is_deceased) {
            return false;
        }

        return Membership::query()->where('lodge_id', $lodge->id)->where('person_id', $person->id)
            ->whereNull('end_date')->whereHas('status', fn ($query) => $query->where('key', 'active'))->exists();
    }
}
