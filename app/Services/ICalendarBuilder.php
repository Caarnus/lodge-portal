<?php

namespace App\Services;

use App\Models\EventOccurrence;

class ICalendarBuilder
{
    /** @param iterable<EventOccurrence> $occurrences */
    public function build(iterable $occurrences): string
    {
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//WorkingTools//Lodge Portal//EN', 'CALSCALE:GREGORIAN'];
        foreach ($occurrences as $occurrence) {
            $event = $occurrence->event;
            $lines = [...$lines, 'BEGIN:VEVENT', 'UID:'.$occurrence->id.'@workingtools', 'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'), 'DTSTART:'.$occurrence->starts_at->utc()->format('Ymd\THis\Z'), 'DTEND:'.$occurrence->ends_at->utc()->format('Ymd\THis\Z'), 'SUMMARY:'.$this->escape($occurrence->title_override ?: $event->title), 'LOCATION:'.$this->escape($occurrence->location_name_override ?: ($event->location_name ?? '')), 'END:VEVENT'];
        }

        return implode("\r\n", [...$lines, 'END:VCALENDAR', '']);
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', ''], $value);
    }
}
