<?php

namespace App\Enums;

enum EventReservationStatus: string
{
    case Confirmed = 'confirmed';
    case AttendeeCancelled = 'attendee_cancelled';
    case EventCancelled = 'event_cancelled';
    case AdministrativelyCancelled = 'administratively_cancelled';
}
