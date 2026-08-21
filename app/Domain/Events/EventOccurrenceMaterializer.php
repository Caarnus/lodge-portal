<?php

namespace App\Domain\Events;

use App\Models\Event;
use App\Models\EventOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class EventOccurrenceMaterializer
{
    public function __construct(private readonly RecurrenceExpander $recurrence) {}

    /** @return list<EventOccurrence> */
    public function materialize(Event $event, CarbonImmutable $from, CarbonImmutable $through): array
    {
        return DB::transaction(function () use ($event, $from, $through): array {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $occurrences = [];

            foreach ($this->recurrence->expand($event, $from, $through) as $candidate) {
                $occurrence = EventOccurrence::query()->firstOrNew([
                    'event_id' => $event->id,
                    'recurrence_key' => $candidate->recurrenceKey,
                ]);

                if (! $occurrence->exists) {
                    $occurrence->fill([
                        'lodge_id' => $event->lodge_id,
                        'original_starts_at' => $candidate->originalStartsAt,
                        'starts_at' => $candidate->startsAt,
                        'ends_at' => $candidate->endsAt,
                    ])->save();
                }

                $occurrences[] = $occurrence;
            }

            $event->forceFill(['occurrences_generated_through' => $through])->save();

            return $occurrences;
        });
    }
}
