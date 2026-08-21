<?php

namespace App\Http\Controllers;

use App\Enums\EventOccurrenceStatus;
use App\Enums\EventReservationStatus;
use App\Enums\ReminderDeliveryStatus;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Lodge;
use App\Services\Audit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventOccurrenceController extends Controller
{
    public function index(Lodge $lodge, Event $event)
    {
        $this->allow($lodge, $event);

        return Inertia::render('events/Occurrences', [
            'lodge' => $lodge->only('id', 'name', 'timezone'),
            'event' => $event->only('id', 'title'),
            'occurrences' => $event->occurrences()->orderBy('starts_at')->paginate(50),
        ]);
    }

    public function update(Request $request, Lodge $lodge, Event $event, EventOccurrence $occurrence)
    {
        $this->allowOccurrence($lodge, $event, $occurrence);
        $data = $request->validate([
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'title_override' => ['nullable', 'string', 'max:255'], 'description_override' => ['nullable', 'string'],
            'location_name_override' => ['nullable', 'string', 'max:255'], 'location_details_override' => ['nullable', 'string'],
        ]);
        $before = $occurrence->toArray();
        $occurrence->fill($data + ['overridden_at' => now()])->save();
        Audit::record('event_occurrence.updated', $occurrence, $lodge, $before, $occurrence->fresh()->toArray());

        return back()->with('notice', 'Occurrence updated.');
    }

    public function cancel(Lodge $lodge, Event $event, EventOccurrence $occurrence)
    {
        $this->allowOccurrence($lodge, $event, $occurrence);
        $occurrence->update(['status' => EventOccurrenceStatus::Cancelled, 'cancelled_at' => now()]);
        $occurrence->reservations()->where('status', EventReservationStatus::Confirmed)->update(['status' => EventReservationStatus::EventCancelled, 'cancelled_at' => now()]);
        $occurrence->reminderDeliveries()->whereIn('status', [ReminderDeliveryStatus::Pending, ReminderDeliveryStatus::Claimed])->update(['status' => ReminderDeliveryStatus::Skipped, 'skipped_at' => now()]);

        return back()->with('notice', 'Occurrence cancelled.');
    }

    public function restore(Lodge $lodge, Event $event, EventOccurrence $occurrence)
    {
        $this->allowOccurrence($lodge, $event, $occurrence);
        $occurrence->update(['status' => EventOccurrenceStatus::Scheduled, 'cancelled_at' => null]);

        return back()->with('notice', 'Occurrence restored.');
    }

    private function allow(Lodge $lodge, Event $event): void
    {
        abort_unless($event->lodge_id === $lodge->id, 404);
        abort_unless(request()->user()?->hasLodgePermission($lodge, 'events.manage'), 403);
    }

    private function allowOccurrence(Lodge $lodge, Event $event, EventOccurrence $occurrence): void
    {
        $this->allow($lodge, $event);
        abort_unless($occurrence->event_id === $event->id && $occurrence->lodge_id === $lodge->id, 404);
    }
}
