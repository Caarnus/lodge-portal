<?php

namespace App\Http\Controllers;

use App\Enums\EventReservationStatus;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventReservation;
use App\Models\Lodge;
use Inertia\Inertia;

class EventReservationController extends Controller
{
    public function index(Lodge $lodge, Event $event, EventOccurrence $occurrence)
    {
        abort_unless($event->lodge_id === $lodge->id && $occurrence->event_id === $event->id && $occurrence->lodge_id === $lodge->id, 404);
        abort_unless(request()->user()?->hasLodgePermission($lodge, 'events.manage'), 403);

        return Inertia::render('events/Reservations', ['lodge' => $lodge->only('id', 'name'), 'event' => $event->only('id', 'title'), 'occurrence' => $occurrence->only('id', 'starts_at'), 'reservations' => $occurrence->reservations()->orderBy('created_at')->paginate(50)]);
    }

    public function cancel(Lodge $lodge, Event $event, EventOccurrence $occurrence, EventReservation $reservation)
    {
        abort_unless($event->lodge_id === $lodge->id && $occurrence->event_id === $event->id && $occurrence->lodge_id === $lodge->id && $reservation->event_occurrence_id === $occurrence->id && $reservation->event_id === $event->id && $reservation->lodge_id === $lodge->id, 404);
        abort_unless(request()->user()?->hasLodgePermission($lodge, 'events.manage'), 403);
        if ($reservation->status === EventReservationStatus::Confirmed) {
            $reservation->update(['status' => EventReservationStatus::AdministrativelyCancelled, 'cancelled_at' => now()]);
        }

        return back()->with('notice', 'Reservation cancelled.');
    }
}
