<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventReminderRule extends Model
{
    protected $guarded = [];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function deliveries()
    {
        return $this->hasMany(EventReminderDelivery::class);
    }
}
