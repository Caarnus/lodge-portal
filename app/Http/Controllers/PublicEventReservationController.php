<?php

namespace App\Http\Controllers;

use App\Domain\Events\EventReservationService;
use App\Enums\EventOccurrenceStatus;
use App\Enums\LodgeStatus;
use App\Models\EventOccurrence;
use App\Models\Lodge;
use App\Notifications\EventReservationConfirmation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class PublicEventReservationController extends Controller
{
    public function store(Request $request, Lodge $lodge, EventOccurrence $occurrence, EventReservationService $reservations)
    {
        abort_unless($lodge->status === LodgeStatus::Active && $occurrence->lodge_id === $lodge->id, 404);
        $occurrence->load('event');
        abort_unless($occurrence->status === EventOccurrenceStatus::Scheduled, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email:rfc', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'party_size' => ['required', 'integer', 'min:1']]);
        $result = $reservations->reserve($occurrence, $request->user(), $data);
        $result->reservation->load(['event', 'lodge']);
        Notification::route('mail', [$result->reservation->email => $result->reservation->name])
            ->notify(new EventReservationConfirmation($result->reservation, $result->cancellationToken));

        return back()->with('notice', 'Your reservation is confirmed.');
    }
}
