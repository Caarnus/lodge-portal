<?php

namespace App\Http\Controllers\Settings;

use App\Enums\LodgeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Services\SelfProfileService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request, SelfProfileService $profiles): Response
    {
        $person = $profiles->personFor($request->user());

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'profile' => [
                'preferred_name' => $person->preferred_name,
                'email' => $person->email ?? $request->user()->email,
                'phone' => $person->phone,
                'mailing_address_line_1' => $person->mailing_address_line_1,
                'mailing_address_line_2' => $person->mailing_address_line_2,
                'mailing_city' => $person->mailing_city,
                'mailing_state' => $person->mailing_state,
                'mailing_postal_code' => $person->mailing_postal_code,
            ],
            'directoryPrivacy' => $person->directoryPrivacySetting()->firstOrCreate()->only([
                'scope', 'show_email', 'show_phone', 'show_address', 'show_profile_photo', 'show_degree',
            ]),
            'communicationPreferences' => $person->memberships()->with(['lodge', 'communicationPreference', 'status'])
                ->get()->filter(fn ($membership) => $membership->isActive() && $membership->lodge?->status === LodgeStatus::Active)
                ->map(fn ($membership) => [
                    'membership_id' => $membership->id,
                    'lodge_name' => $membership->lodge->name,
                    'lodge_number' => $membership->lodge->number,
                    'receives_lodge_email' => $membership->communicationPreference?->receives_lodge_email ?? true,
                    'receives_print_newsletter' => $membership->communicationPreference?->receives_print_newsletter ?? false,
                    'has_complete_mailing_address' => filled($person->mailing_address_line_1) && filled($person->mailing_city) && filled($person->mailing_state) && filled($person->mailing_postal_code),
                ])->values(),
            'photo' => [
                'status' => $person->profile_photo_status,
                'error' => $person->profile_photo_error,
                'ready' => $person->profile_photo_status === 'ready' && filled($person->profile_photo_derivative_path),
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, SelfProfileService $profiles): RedirectResponse
    {
        $profiles->update($request->user(), $request->validated());

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(Request $request, SelfProfileService $profiles): RedirectResponse
    {
        $profiles->personFor($request->user());
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
