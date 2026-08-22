<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventVolunteerPosition extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['needed_count' => 'integer', 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function occurrence()
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    public function commitments()
    {
        return $this->hasMany(EventVolunteerCommitment::class);
    }
}
