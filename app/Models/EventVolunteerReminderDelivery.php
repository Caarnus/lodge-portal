<?php

namespace App\Models;

use App\Enums\VolunteerReminderDeliveryStatus;
use Illuminate\Database\Eloquent\Model;

class EventVolunteerReminderDelivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => VolunteerReminderDeliveryStatus::class, 'due_at' => 'datetime', 'claimed_at' => 'datetime', 'attempted_at' => 'datetime', 'sent_at' => 'datetime', 'skipped_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function commitment()
    {
        return $this->belongsTo(EventVolunteerCommitment::class, 'event_volunteer_commitment_id');
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
}
