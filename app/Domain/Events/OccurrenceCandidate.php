<?php

namespace App\Domain\Events;

use Carbon\CarbonImmutable;

readonly class OccurrenceCandidate
{
    public function __construct(
        public string          $recurrenceKey,
        public CarbonImmutable $originalStartsAt,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
    )
    {
    }
}
