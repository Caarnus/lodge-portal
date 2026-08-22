<?php

namespace App\Models;

use App\Enums\EventQualification;
use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'visibility' => EventVisibility::class,
            'required_qualification' => EventQualification::class,
            'allows_cross_lodge_reservations' => 'boolean',
            'reservations_enabled' => 'boolean',
            'guest_reservations_enabled' => 'boolean',
            'reminders_enabled' => 'boolean',
            'guest_reminders_enabled' => 'boolean',
            'first_starts_at' => 'datetime',
            'occurrences_generated_through' => 'datetime',
            'published_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function category()
    {
        return $this->belongsTo(EventCategory::class, 'event_category_id');
    }

    public function coverMediaAsset()
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_asset_id');
    }

    public function occurrences()
    {
        return $this->hasMany(EventOccurrence::class);
    }

    public function reservationFields()
    {
        return $this->hasMany(EventReservationField::class)->orderBy('sort_order');
    }

    public function reservations()
    {
        return $this->hasMany(EventReservation::class);
    }

    public function reminderRules()
    {
        return $this->hasMany(EventReminderRule::class)->orderBy('offset_minutes');
    }

    public function reminderSubscriptions()
    {
        return $this->hasMany(EventReminderSubscription::class);
    }

    public function volunteerPositions()
    {
        return $this->hasMany(EventVolunteerPosition::class);
    }

    public function volunteerCommitments()
    {
        return $this->hasMany(EventVolunteerCommitment::class);
    }
}
