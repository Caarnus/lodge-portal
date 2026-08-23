<?php

namespace App\Http\Controllers;

use App\Models\Lodge;
use App\Models\Membership;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MembershipCommunicationPreferenceController extends Controller
{
    public function update(Request $request, Lodge $lodge, Membership $membership)
    {
        $this->allowLodge($lodge, 'communications.recipients');
        abort_unless($membership->lodge_id === $lodge->id, 404);
        $data = $request->validate(['receives_lodge_email' => ['required', 'boolean'], 'receives_print_newsletter' => ['required', 'boolean']]);
        $person = $membership->person;
        if ($data['receives_print_newsletter'] && (! filled($person?->mailing_address_line_1) || ! filled($person?->mailing_city) || ! filled($person?->mailing_state) || ! filled($person?->mailing_postal_code))) {
            throw ValidationException::withMessages(['receives_print_newsletter' => 'A complete mailing address is required for a mailed newsletter.']);
        }
        $preference = $membership->communicationPreference()->firstOrCreate(['lodge_id' => $lodge->id]);
        $before = $preference->only(['receives_lodge_email', 'receives_print_newsletter']);
        $preference->update($data + ['updated_by' => $request->user()->id]);
        Audit::record('membership_communication_preference.admin_updated', $membership, $lodge, $before, $preference->fresh()->only(['receives_lodge_email', 'receives_print_newsletter']));

        return back();
    }
}
