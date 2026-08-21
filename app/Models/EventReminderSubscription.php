<?php

namespace App\Models;

use App\Enums\ReminderSubscriptionStatus;
use Illuminate\Database\Eloquent\Model;

class EventReminderSubscription extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => ReminderSubscriptionStatus::class, 'unsubscribed_at' => 'datetime'];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function occurrence()
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function deliveries()
    {
        return $this->hasMany(EventReminderDelivery::class);
    }
}
