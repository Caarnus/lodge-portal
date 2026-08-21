<?php

namespace App\Models;

use App\Enums\EventOccurrenceStatus;
use Illuminate\Database\Eloquent\Model;

class EventOccurrence extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => EventOccurrenceStatus::class,
            'original_starts_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'overridden_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function reservations()
    {
        return $this->hasMany(EventReservation::class);
    }

    public function reminderSubscriptions()
    {
        return $this->hasMany(EventReminderSubscription::class);
    }

    public function reminderDeliveries()
    {
        return $this->hasMany(EventReminderDelivery::class);
    }
}
