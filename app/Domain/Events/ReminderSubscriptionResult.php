<?php

namespace App\Domain\Events;

use App\Models\EventReminderSubscription;

readonly class ReminderSubscriptionResult
{
    public function __construct(public EventReminderSubscription $subscription, public string $unsubscribeToken)
    {
    }
}
