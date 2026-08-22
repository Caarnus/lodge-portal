<?php

namespace App\Domain\Events;

use App\Models\Event;
use App\Models\EventOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class EventScheduleReconciler
{
    public function __construct(private readonly EventOccurrenceMaterializer $materializer) {}

    /** @return list<EventOccurrence> */
    public function reconcile(Event $event, CarbonImmutable $from, CarbonImmutable $through): array
    {
        return DB::transaction(function () use ($event, $from, $through): array {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);

            $event->occurrences()
                ->where('original_starts_at', '>=', now())
                ->whereNull('overridden_at')
                ->where('status', 'scheduled')
                ->whereDoesntHave('reservations')
                ->whereDoesntHave('reminderSubscriptions')
                ->whereDoesntHave('reminderDeliveries')
                ->whereDoesntHave('volunteerPositions')
                ->whereDoesntHave('volunteerCommitments')
                ->whereDoesntHave('volunteerReminderDeliveries')
                ->delete();

            return $this->materializer->materialize($event, $from, $through);
        });
    }
}
