<?php

namespace App\Enums;

enum EventOccurrenceStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
}
