<?php

namespace App\Domain\Events;

use App\Models\EventReservation;

readonly class ReservationResult
{
    public function __construct(public EventReservation $reservation, public string $cancellationToken) {}
}
