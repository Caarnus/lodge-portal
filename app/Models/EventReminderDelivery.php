<?php

namespace App\Models;

use App\Enums\ReminderDeliveryStatus;
use Illuminate\Database\Eloquent\Model;

class EventReminderDelivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ReminderDeliveryStatus::class,
            'due_at' => 'datetime',
            'claimed_at' => 'datetime',
            'sent_at' => 'datetime',
            'skipped_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function subscription()
    {
        return $this->belongsTo(EventReminderSubscription::class, 'event_reminder_subscription_id');
    }

    public function rule()
    {
        return $this->belongsTo(EventReminderRule::class, 'event_reminder_rule_id');
    }

    public function occurrence()
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }
}
