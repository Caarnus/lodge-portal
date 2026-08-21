<?php

namespace App\Http\Controllers;

use App\Domain\Events\EventReminderSubscriptionService;
use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\LodgeStatus;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Lodge;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicEventReminderController extends Controller
{
    public function store(Request $request, Lodge $lodge, Event $event, EventReminderSubscriptionService $subscriptions)
    {
        abort_unless($lodge->status === LodgeStatus::Active && $event->lodge_id === $lodge->id, 404);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'scope' => ['required', Rule::in(['occurrence', 'series'])],
            'occurrence_id' => ['required_if:scope,occurrence', 'nullable', 'integer'],
        ]);

        $occurrence = null;
        if ($data['scope'] === 'occurrence') {
            $occurrence = EventOccurrence::query()
                ->whereKey($data['occurrence_id'])
                ->where('event_id', $event->id)
                ->where('lodge_id', $lodge->id)
                ->where('status', EventOccurrenceStatus::Scheduled)
                ->firstOrFail();
        } else {
            abort_unless($event->rrule !== null, 422);
        }

        abort_unless($event->status === EventStatus::Published, 404);
        $subscriptions->subscribe($event, $occurrence, $request->user(), $data);

        return back()->with('notice', 'Your reminder subscription is active.');
    }
}
