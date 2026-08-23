<?php

namespace App\Domain\Events;

use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\ReminderDeliveryStatus;
use App\Enums\ReminderSubscriptionStatus;
use App\Jobs\SendEventReminderDelivery;
use App\Models\EventOccurrence;
use App\Models\EventReminderDelivery;
use App\Models\EventReminderSubscription;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

class EventReminderDispatcher
{
    public function dispatchDue(CarbonImmutable $now): int
    {
        $this->createMissingDeliveries($now);

        $deliveryIds = EventReminderDelivery::query()
            ->where('status', ReminderDeliveryStatus::Pending)
            ->where('due_at', '<=', $now)
            ->orderBy('id')
            ->limit(100)
            ->pluck('id');

        $claimed = 0;
        foreach ($deliveryIds as $deliveryId) {
            $updated = EventReminderDelivery::query()
                ->whereKey($deliveryId)
                ->where('status', ReminderDeliveryStatus::Pending)
                ->update(['status' => ReminderDeliveryStatus::Claimed, 'claimed_at' => $now]);

            if ($updated === 1) {
                SendEventReminderDelivery::dispatch($deliveryId);
                $claimed++;
            }
        }

        return $claimed;
    }

    private function createMissingDeliveries(CarbonImmutable $now): void
    {
        EventOccurrence::query()
            ->with(['event.reminderRules', 'event.reminderSubscriptions'])
            ->where('status', EventOccurrenceStatus::Scheduled)
            ->where('starts_at', '>=', $now)
            ->whereHas('event', fn($query) => $query->where('status', EventStatus::Published)->where('reminders_enabled', true))
            ->orderBy('id')
            ->each(function (EventOccurrence $occurrence): void {
                $event = $occurrence->event;
                $subscriptions = $event->reminderSubscriptions
                    ->where('status', ReminderSubscriptionStatus::Active)
                    ->filter(fn(EventReminderSubscription $subscription) => $subscription->event_occurrence_id === null || $subscription->event_occurrence_id === $occurrence->id)
                    ->groupBy('normalized_email')
                    ->map(fn($subscriptions) => $subscriptions->first(fn(EventReminderSubscription $subscription) => $subscription->event_occurrence_id === $occurrence->id) ?? $subscriptions->first());

                foreach ($subscriptions as $subscription) {
                    foreach ($event->reminderRules as $rule) {
                        try {
                            EventReminderDelivery::query()->firstOrCreate([
                                'event_reminder_subscription_id' => $subscription->id,
                                'event_reminder_rule_id' => $rule->id,
                                'event_occurrence_id' => $occurrence->id,
                            ], [
                                'event_id' => $event->id,
                                'lodge_id' => $event->lodge_id,
                                'normalized_email' => $subscription->normalized_email,
                                'due_at' => $occurrence->starts_at->copy()->subMinutes($rule->offset_minutes),
                                'status' => ReminderDeliveryStatus::Pending,
                            ]);
                        } catch (QueryException) {
                            // A concurrent dispatcher may win the unique delivery key race.
                        }
                    }
                }
            });
    }
}
