<?php

namespace App\Http\Controllers;

use App\Domain\Events\EventReminderSubscriptionService;
use App\Domain\Events\EventReservationService;
use App\Enums\EventOccurrenceStatus;
use App\Enums\LodgeStatus;
use App\Models\EventOccurrence;
use App\Models\Lodge;
use App\Notifications\EventReminderSubscriptionConfirmation;
use App\Notifications\EventReservationConfirmation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class PublicEventReservationController extends Controller
{
    public function store(Request $request, Lodge $lodge, EventOccurrence $occurrence, EventReservationService $reservations, EventReminderSubscriptionService $subscriptions)
    {
        abort_unless($lodge->status === LodgeStatus::Active && $occurrence->lodge_id === $lodge->id, 404);
        $occurrence->load('event');
        abort_unless($occurrence->status === EventOccurrenceStatus::Scheduled, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email:rfc', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'party_size' => ['required', 'integer', 'min:1'], 'responses' => ['nullable', 'array'], 'subscribe_to_reminders' => ['nullable', 'boolean']]);
        $result = $reservations->reserve($occurrence, $request->user(), $data);
        $result->reservation->load(['event', 'lodge']);
        Notification::route('mail', [$result->reservation->email => $result->reservation->name])
            ->notify(new EventReservationConfirmation($result->reservation, $result->cancellationToken));
        if ($request->boolean('subscribe_to_reminders')) {
            $subscription = $subscriptions->subscribe($occurrence->event, $occurrence, $request->user(), $data);
            $subscription->subscription->load(['event', 'lodge']);
            Notification::route('mail', [$subscription->subscription->email => $subscription->subscription->name])
                ->notify(new EventReminderSubscriptionConfirmation($subscription->subscription, $subscription->unsubscribeToken));
        }

        return back()->with('notice', 'Your reservation is confirmed.');
    }
}
