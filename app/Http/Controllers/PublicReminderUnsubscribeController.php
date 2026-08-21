<?php

namespace App\Http\Controllers;

use App\Enums\ReminderSubscriptionStatus;
use App\Models\EventReminderSubscription;
use App\Models\Lodge;

class PublicReminderUnsubscribeController extends Controller
{
    public function store(Lodge $lodge, string $token)
    {
        $subscription = EventReminderSubscription::query()->where('lodge_id', $lodge->id)->where('unsubscribe_token_hash', hash('sha256', $token))->firstOrFail();
        if ($subscription->status === ReminderSubscriptionStatus::Active) {
            $subscription->update(['status' => ReminderSubscriptionStatus::Unsubscribed, 'unsubscribed_at' => now()]);
        }

        return redirect()->route('public.events.index', $lodge)->with('notice', 'Reminder subscription removed.');
    }
}
