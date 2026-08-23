<?php

namespace App\Http\Controllers;

use App\Models\CommunicationDelivery;
use App\Models\Lodge;
use App\Services\Audit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicCommunicationUnsubscribeController extends Controller
{
    public function show(Lodge $lodge, string $token)
    {
        return Inertia::render('public/CommunicationUnsubscribe', ['lodge' => $lodge, 'token' => $token]);
    }

    public function store(Request $request, Lodge $lodge, string $token)
    {
        $delivery = CommunicationDelivery::query()->where('lodge_id', $lodge->id)->where('unsubscribe_token_hash', hash('sha256', $token))->first();
        if ($delivery?->membership) {
            $delivery->membership->communicationPreference()->updateOrCreate(['lodge_id' => $lodge->id], ['receives_lodge_email' => false]);
        }
        if ($delivery?->familyNewsletterSubscription) {
            $subscription = $delivery->familyNewsletterSubscription;
            $subscription->update(['receives_email' => false, 'status' => $subscription->receives_print ? 'active' : 'unsubscribed', 'unsubscribed_at' => now()]);
        }
        if ($delivery) {
            Audit::record('communication_delivery.unsubscribed', $delivery, $lodge, null, ['id' => $delivery->id]);
        }

        return redirect()->route('home')->with('notice', 'Your lodge-email preference has been updated.');
    }
}
