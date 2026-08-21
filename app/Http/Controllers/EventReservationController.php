<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventOccurrence;
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
}
