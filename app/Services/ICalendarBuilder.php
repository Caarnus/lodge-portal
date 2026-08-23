<?php

namespace App\Services;

use App\Enums\EventOccurrenceStatus;
use App\Models\Event;
use App\Models\EventOccurrence;

class ICalendarBuilder
{
    /** @param iterable<EventOccurrence> $occurrences */
    public function build(iterable $occurrences): string
    {
        $lines = $this->header();
        foreach ($occurrences as $occurrence) {
            $lines = [...$lines, ...$this->occurrenceLines($occurrence)];
        }

        return $this->serialize([...$lines, 'END:VCALENDAR']);
    }

    public function buildSeries(Event $event): string
    {
        $event->loadMissing('occurrences');
        $zone = $event->time_zone;
        $start = $event->first_starts_at->copy()->setTimezone($zone);
        $uid = $this->seriesUid($event);
        $lines = [...$this->header(), 'BEGIN:VEVENT', 'UID:' . $uid, 'DTSTAMP:' . now()->utc()->format('Ymd\THis\Z'), "DTSTART;TZID={$zone}:" . $start->format('Ymd\THis'), 'DURATION:PT' . $event->duration_minutes . 'M', 'SUMMARY:' . $this->escape($event->title)];
        if ($event->location_name) {
            $lines[] = 'LOCATION:' . $this->escape($event->location_name);
        }
        if ($event->description) {
            $lines[] = 'DESCRIPTION:' . $this->escape(strip_tags($event->description));
        }
        if ($event->rrule) {
            $lines[] = 'RRULE:' . $event->rrule;
        }
        foreach ($event->occurrences->where('status', EventOccurrenceStatus::Cancelled) as $occurrence) {
            $lines[] = "EXDATE;TZID={$zone}:" . $occurrence->original_starts_at->copy()->setTimezone($zone)->format('Ymd\THis');
        }
        $lines[] = 'END:VEVENT';
        foreach ($event->occurrences->where('status', EventOccurrenceStatus::Scheduled)->filter(fn(EventOccurrence $occurrence) => $occurrence->overridden_at !== null) as $occurrence) {
            $lines = [...$lines, 'BEGIN:VEVENT', 'UID:' . $uid, "RECURRENCE-ID;TZID={$zone}:" . $occurrence->original_starts_at->copy()->setTimezone($zone)->format('Ymd\THis'), "DTSTART;TZID={$zone}:" . $occurrence->starts_at->copy()->setTimezone($zone)->format('Ymd\THis'), "DTEND;TZID={$zone}:" . $occurrence->ends_at->copy()->setTimezone($zone)->format('Ymd\THis'), 'SUMMARY:' . $this->escape($occurrence->title_override ?: $event->title), 'LOCATION:' . $this->escape($occurrence->location_name_override ?: ($event->location_name ?? '')), 'END:VEVENT'];
        }

        return $this->serialize([...$lines, 'END:VCALENDAR']);
    }

    /** @return list<string> */
    private function header(): array
    {
        return ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//WorkingTools//Lodge Portal//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH'];
    }

    /** @return list<string> */
    private function occurrenceLines(EventOccurrence $occurrence): array
    {
        $event = $occurrence->event;

        return ['BEGIN:VEVENT', 'UID:event-' . $event->id . '-' . $occurrence->recurrence_key . '@lodge-portal', 'DTSTAMP:' . now()->utc()->format('Ymd\THis\Z'), 'DTSTART:' . $occurrence->starts_at->utc()->format('Ymd\THis\Z'), 'DTEND:' . $occurrence->ends_at->utc()->format('Ymd\THis\Z'), 'SUMMARY:' . $this->escape($occurrence->title_override ?: $event->title), 'DESCRIPTION:' . $this->escape(strip_tags($occurrence->description_override ?: ($event->description ?? ''))), 'LOCATION:' . $this->escape($occurrence->location_name_override ?: ($event->location_name ?? '')), 'END:VEVENT'];
    }

    private function seriesUid(Event $event): string
    {
        return "event-{$event->id}@lodge-portal";
    }

    private function serialize(array $lines): string
    {
        return implode("\r\n", array_merge(...array_map(fn(string $line) => $this->fold($line), $lines))) . "\r\n";
    }

    /** @return list<string> */
    private function fold(string $line): array
    {
        $parts = [];
        while (strlen($line) > 75) {
            $parts[] = substr($line, 0, 75);
            $line = ' ' . substr($line, 75);
        }
        $parts[] = $line;

        return $parts;
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', ''], $value);
    }
}
