<?php

namespace App\Http\Controllers;

use App\Enums\ReminderDeliveryStatus;
use App\Jobs\SendEventReminderDelivery;
use App\Models\Event;
use App\Models\EventReminderDelivery;
use App\Models\Lodge;

class EventReminderDeliveryController extends Controller
{
    public function retry(Lodge $lodge, Event $event, EventReminderDelivery $delivery)
    {
        abort_unless($event->lodge_id === $lodge->id && $delivery->event_id === $event->id && $delivery->lodge_id === $lodge->id, 404);
        abort_unless(request()->user()?->hasLodgePermission($lodge, 'events.manage'), 403);
        abort_unless($delivery->status === ReminderDeliveryStatus::Failed, 422);
        $delivery->update(['status' => ReminderDeliveryStatus::Claimed, 'claimed_at' => now(), 'failed_at' => null, 'last_error' => null]);
        SendEventReminderDelivery::dispatch($delivery->id);

        return back()->with('notice', 'Reminder delivery queued for retry.');
    }
}
