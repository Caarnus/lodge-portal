<?php

namespace App\Jobs;

use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\ReminderDeliveryStatus;
use App\Enums\ReminderSubscriptionStatus;
use App\Models\EventReminderDelivery;
use App\Notifications\EventReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class SendEventReminderDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(): void
    {
        $delivery = EventReminderDelivery::query()->with(['subscription', 'rule', 'occurrence', 'event', 'lodge'])->find($this->deliveryId);
        if (! $delivery || $delivery->status !== ReminderDeliveryStatus::Claimed) {
            return;
        }
        if (! $this->isDeliverable($delivery)) {
            $delivery->update(['status' => ReminderDeliveryStatus::Skipped, 'skipped_at' => now()]);

            return;
        }

        try {
            Notification::route('mail', [$delivery->subscription->email => $delivery->subscription->name])
                ->notify(new EventReminder($delivery));
            $delivery->update(['status' => ReminderDeliveryStatus::Sent, 'sent_at' => now(), 'last_error' => null]);
        } catch (\Throwable $exception) {
            $delivery->update(['status' => ReminderDeliveryStatus::Failed, 'failed_at' => now(), 'last_error' => str($exception->getMessage())->limit(1000)]);
        }
    }

    private function isDeliverable(EventReminderDelivery $delivery): bool
    {
        return $delivery->event_id === $delivery->occurrence?->event_id
            && $delivery->event_id === $delivery->subscription?->event_id
            && $delivery->event_id === $delivery->rule?->event_id
            && $delivery->lodge_id === $delivery->event?->lodge_id
            && $delivery->lodge_id === $delivery->occurrence?->lodge_id
            && $delivery->lodge_id === $delivery->subscription?->lodge_id
            && $delivery->lodge_id === $delivery->rule?->lodge_id
            && $delivery->event?->status === EventStatus::Published
            && $delivery->occurrence?->status === EventOccurrenceStatus::Scheduled
            && $delivery->subscription?->status === ReminderSubscriptionStatus::Active;
    }
}
