<?php

namespace App\Domain\Galleries;

use App\Enums\GalleryVisibility;
use App\Enums\LodgeStatus;
use App\Models\GalleryAlbumVersion;
use App\Models\Lodge;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class GalleryAudience
{
    public function visible(Builder $query, Lodge $lodge, ?User $user): Builder
    {
        if ($lodge->status !== LodgeStatus::Active) {
            return $query->whereRaw('1 = 0');
        }
        if (!$this->activeMason($user)) {
            return $query->where('visibility', GalleryVisibility::Public);
        }
        if (!$this->activeMembership($user, $lodge)) {
            return $query->whereIn('visibility', [GalleryVisibility::Public, GalleryVisibility::Masons]);
        }

        return $query;
    }

    public function canView(?User $user, Lodge $lodge, GalleryAlbumVersion $version): bool
    {
        return $this->visible(GalleryAlbumVersion::query()->whereKey($version->id), $lodge, $user)->exists();
    }

    private function activeMason(?User $user): bool
    {
        $person = $user?->person;

        return $user && $user->approval_status === 'approved' && $user->hasVerifiedEmail() && $person && !$person->trashed() && !$person->merged_at && !$person->is_deceased
            && Membership::query()->where('person_id', $person->id)->whereNull('end_date')->whereHas('status', fn($q) => $q->where('key', 'active'))
                ->whereHas('degree', fn($q) => $q->where('key', 'master_mason'))->exists();
    }

    private function activeMembership(?User $user, Lodge $lodge): bool
    {
        return $user?->person && Membership::query()->where('person_id', $user->person_id)->where('lodge_id', $lodge->id)->whereNull('end_date')->whereHas('status', fn($q) => $q->where('key', 'active'))->exists();
    }
}
