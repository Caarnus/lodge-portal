<?php

namespace App\Domain\Events;

use App\Models\Event;
use Carbon\CarbonImmutable;

interface RecurrenceExpander
{
    public function canonicalize(string $rule, CarbonImmutable $startsAt, string $timeZone): string;

    /** @return list<OccurrenceCandidate> */
    public function expand(Event $event, CarbonImmutable $from, CarbonImmutable $through, int $limit = 1000): array;

    public function describe(string $rule, CarbonImmutable $startsAt, string $timeZone): string;
}
