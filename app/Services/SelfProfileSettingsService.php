<?php

namespace App\Services;

use App\Enums\LodgeStatus;
use App\Models\Membership;
use App\Models\MembershipCommunicationPreference;
use App\Models\PersonDirectoryPrivacySetting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SelfProfileSettingsService
{
    public function __construct(private readonly SelfProfileService $profiles) {}

    public function updateDirectoryPrivacy(User $actor, array $data): PersonDirectoryPrivacySetting
    {
        $person = $this->profiles->personFor($actor);

        return DB::transaction(function () use ($actor, $person, $data) {
            $setting = PersonDirectoryPrivacySetting::query()->lockForUpdate()->firstOrCreate([
                'person_id' => $person->id,
            ]);
            $before = $this->privacyValues($setting);
            $setting->fill(Arr::only($data, array_keys($before)) + ['updated_by' => $actor->id]);
            $setting->save();
            $after = $this->privacyValues($setting->fresh());
            Audit::record('directory_privacy.updated', $person, null, $before, $after);

            return $setting->fresh();
        });
    }

    public function updateCommunicationPreference(User $actor, int $membershipId, bool $receivesLodgeEmail): MembershipCommunicationPreference
    {
        $person = $this->profiles->personFor($actor);

        return DB::transaction(function () use ($actor, $person, $membershipId, $receivesLodgeEmail) {
            $membership = Membership::query()->with('lodge')->lockForUpdate()
                ->whereKey($membershipId)->where('person_id', $person->id)->first();
            if (! $membership || ! $membership->isActive() || $membership->lodge?->status !== LodgeStatus::Active) {
                throw new AuthorizationException('That active membership is not available for your communication settings.');
            }

            $preference = MembershipCommunicationPreference::query()->lockForUpdate()
                ->where('membership_id', $membership->id)->first()
                ?? MembershipCommunicationPreference::create([
                    'membership_id' => $membership->id,
                    'lodge_id' => $membership->lodge_id,
                ]);
            $before = ['receives_lodge_email' => $preference->receives_lodge_email];
            $preference->update(['receives_lodge_email' => $receivesLodgeEmail, 'updated_by' => $actor->id]);
            $after = ['receives_lodge_email' => $preference->fresh()->receives_lodge_email];
            Audit::record('membership_communication_preference.updated', $membership, $membership->lodge, [
                'person_id' => $person->id,
                'membership_id' => $membership->id,
                'lodge_id' => $membership->lodge_id,
                ...$before,
            ], [
                'person_id' => $person->id,
                'membership_id' => $membership->id,
                'lodge_id' => $membership->lodge_id,
                ...$after,
            ]);

            return $preference->fresh();
        });
    }

    private function privacyValues(PersonDirectoryPrivacySetting $setting): array
    {
        return Arr::only($setting->toArray(), [
            'scope', 'show_email', 'show_phone', 'show_address', 'show_profile_photo', 'show_degree',
        ]);
    }
}
