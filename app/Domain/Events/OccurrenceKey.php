<?php

namespace App\Domain\Events;

use DateTimeInterface;

class OccurrenceKey
{
    public static function fromLocalStart(DateTimeInterface $startsAt): string
    {
        return $startsAt->format('Ymd\\THis');
    }
}
