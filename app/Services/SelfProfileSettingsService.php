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
use Illuminate\Validation\ValidationException;

class SelfProfileSettingsService
{
    public function __construct(private readonly SelfProfileService $profiles)
    {
    }

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

    public function updateCommunicationPreference(User $actor, int $membershipId, bool $receivesLodgeEmail, bool $receivesPrintNewsletter): MembershipCommunicationPreference
    {
        $person = $this->profiles->personFor($actor);

        return DB::transaction(function () use ($actor, $person, $membershipId, $receivesLodgeEmail, $receivesPrintNewsletter) {
            $membership = Membership::query()->with('lodge')->lockForUpdate()
                ->whereKey($membershipId)->where('person_id', $person->id)->first();
            if (!$membership || !$membership->isActive() || $membership->lodge?->status !== LodgeStatus::Active) {
                throw new AuthorizationException('That active membership is not available for your communication settings.');
            }
            if ($receivesPrintNewsletter && (!filled($person->mailing_address_line_1) || !filled($person->mailing_city) || !filled($person->mailing_state) || !filled($person->mailing_postal_code))) {
                throw ValidationException::withMessages(['receives_print_newsletter' => 'A complete mailing address is required for a mailed newsletter.']);
            }

            $preference = MembershipCommunicationPreference::query()->lockForUpdate()
                ->where('membership_id', $membership->id)->first()
                ?? MembershipCommunicationPreference::create([
                    'membership_id' => $membership->id,
                    'lodge_id' => $membership->lodge_id,
                ]);
            $before = ['receives_lodge_email' => $preference->receives_lodge_email, 'receives_print_newsletter' => $preference->receives_print_newsletter];
            $preference->update(['receives_lodge_email' => $receivesLodgeEmail, 'receives_print_newsletter' => $receivesPrintNewsletter, 'updated_by' => $actor->id]);
            $after = $preference->fresh()->only(['receives_lodge_email', 'receives_print_newsletter']);
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
