<?php

namespace App\Models;

use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_award_of_gold' => 'boolean',
            'entered_apprentice_date' => 'date',
            'fellow_craft_date' => 'date',
            'master_mason_date' => 'date',
            'affiliation_date' => 'date',
            'demit_withdrawal_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function type()
    {
        return $this->belongsTo(MembershipType::class, 'membership_type_id');
    }

    public function status()
    {
        return $this->belongsTo(MembershipStatus::class, 'membership_status_id');
    }

    public function degree()
    {
        return $this->belongsTo(MasonicDegree::class, 'masonic_degree_id');
    }

    public function officerAssignments()
    {
        return $this->hasMany(OfficerAssignment::class);
    }

    public function communicationPreference()
    {
        return $this->hasOne(MembershipCommunicationPreference::class);
    }

    public function communicationDeliveries()
    {
        return $this->hasMany(CommunicationDelivery::class);
    }

    public function isActive(): bool
    {
        return $this->end_date === null && $this->status?->key === 'active';
    }

    protected static function booted(): void
    {
        static::created(fn(Membership $membership) => $membership->communicationPreference()->firstOrCreate([
            'lodge_id' => $membership->lodge_id,
        ]));
    }
}
