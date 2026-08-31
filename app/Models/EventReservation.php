<?php

namespace App\Models;

use App\Enums\EventReservationStatus;
use Illuminate\Database\Eloquent\Model;

class EventReservation extends Model
{
    protected $guarded = [];

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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    protected function casts(): array
    {
        return ['status' => EventReservationStatus::class, 'responses' => 'array', 'cancelled_at' => 'datetime'];
    }
}
