<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DirectoryPrivacyUpdateRequest;
use App\Http\Requests\Settings\MembershipCommunicationPreferenceUpdateRequest;
use App\Models\Membership;
use App\Services\SelfProfileSettingsService;
use Illuminate\Http\RedirectResponse;

class ProfileSettingsController extends Controller
{
    public function updateDirectoryPrivacy(DirectoryPrivacyUpdateRequest $request, SelfProfileSettingsService $settings): RedirectResponse
    {
        $settings->updateDirectoryPrivacy($request->user(), $request->validated());

        return to_route('profile.edit');
    }

    public function updateCommunicationPreference(MembershipCommunicationPreferenceUpdateRequest $request, Membership $membership, SelfProfileSettingsService $settings): RedirectResponse
    {
        $settings->updateCommunicationPreference($request->user(), $membership->id, $request->boolean('receives_lodge_email'));

        return to_route('profile.edit');
    }
}
