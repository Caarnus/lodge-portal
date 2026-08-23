<?php

namespace App\Domain\Events;

use App\Models\Event;
use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RRule\RRule;

class RruleRecurrenceExpander implements RecurrenceExpander
{
    public function canonicalize(string $rule, CarbonImmutable $startsAt, string $timeZone): string
    {
        $rfc = $this->rule($rule, $startsAt, $timeZone)->rfcString(false);

        $ruleLine = collect(preg_split('/\R/', $rfc) ?: [])
            ->first(fn(string $line) => str_starts_with($line, 'RRULE:'));

        if (!is_string($ruleLine)) {
            throw new InvalidArgumentException('The recurrence rule is invalid.');
        }

        return substr($ruleLine, strlen('RRULE:'));
    }

    public function expand(Event $event, CarbonImmutable $from, CarbonImmutable $through, int $limit = 1000): array
    {
        $startsAt = CarbonImmutable::instance($event->first_starts_at)->setTimezone($event->time_zone);
        $duration = $event->duration_minutes;

        if ($event->rrule === null) {
            if ($startsAt->betweenIncluded($from, $through)) {
                return [$this->candidate($startsAt, $duration)];
            }

            return [];
        }

        $occurrences = $this->rule($event->rrule, $startsAt, $event->time_zone)
            ->getOccurrencesBetween($from->setTimezone($event->time_zone), $through->setTimezone($event->time_zone), $limit + 1);

        if (count($occurrences) > $limit) {
            throw new InvalidArgumentException('The recurrence expansion exceeds the safety limit.');
        }

        return array_map(
            fn($occurrence) => $this->candidate(
                CarbonImmutable::createFromFormat('Y-m-d H:i:s', $occurrence->format('Y-m-d H:i:s'), $event->time_zone),
                $duration,
            ),
            $occurrences,
        );
    }

    public function describe(string $rule, CarbonImmutable $startsAt, string $timeZone): string
    {
        return $this->rule($rule, $startsAt, $timeZone)->humanReadable();
    }

    private function candidate(CarbonImmutable $localStart, int $duration): OccurrenceCandidate
    {
        return new OccurrenceCandidate(
            OccurrenceKey::fromLocalStart($localStart),
            $localStart->utc(),
            $localStart->utc(),
            $localStart->addMinutes($duration)->utc(),
        );
    }

    private function rule(string $rule, CarbonImmutable $startsAt, string $timeZone): RRule
    {
        $normalized = trim($rule);
        $isFullRfcRule = str_starts_with($normalized, 'DTSTART:') || str_contains($normalized, "\nRRULE:");
        if (!$isFullRfcRule && !str_starts_with($normalized, 'RRULE:')) {
            $normalized = 'RRULE:' . $normalized;
        }

        try {
            return $isFullRfcRule
                ? new RRule($normalized)
                : new RRule($normalized, $startsAt->setTimezone(new DateTimeZone($timeZone))->toDateTime());
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('The recurrence rule is invalid.', previous: $exception);
        }
    }
}
