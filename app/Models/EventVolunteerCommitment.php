<?php

namespace App\Models;

use App\Enums\VolunteerCommitmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventVolunteerCommitment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => VolunteerCommitmentStatus::class, 'committed_at' => 'datetime', 'withdrawn_at' => 'datetime', 'administratively_removed_at' => 'datetime'];
    }

    public function position()
    {
        return $this->belongsTo(EventVolunteerPosition::class, 'event_volunteer_position_id');
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function reminderDelivery()
    {
        return $this->hasOne(EventVolunteerReminderDelivery::class, 'event_volunteer_commitment_id');
    }
}
