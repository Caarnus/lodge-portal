<?php

namespace App\Http\Controllers;

use App\Enums\EventReservationStatus;
use App\Models\EventReservation;
use App\Models\Lodge;
use Inertia\Inertia;

class PublicReservationCancellationController extends Controller
{
    public function show(Lodge $lodge, string $token)
    {
        EventReservation::query()->where('lodge_id', $lodge->id)->where('cancellation_token_hash', hash('sha256', $token))->firstOrFail();

        return Inertia::render('public/EventTokenAction', ['lodge' => $lodge->only('name', 'slug'), 'token' => $token, 'kind' => 'reservation']);
    }

    public function store(Lodge $lodge, string $token)
    {
        $reservation = EventReservation::query()->where('lodge_id', $lodge->id)->where('cancellation_token_hash', hash('sha256', $token))->firstOrFail();
        if ($reservation->status === EventReservationStatus::Confirmed) {
            $reservation->update(['status' => EventReservationStatus::AttendeeCancelled, 'cancelled_at' => now()]);
        }

        return redirect()->route('public.events.index', $lodge)->with('notice', 'Reservation cancellation processed.');
    }
}
