<?php

namespace App\Models;

use App\Enums\LodgeStatus;
use Database\Factories\LodgeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lodge extends Model
{
    /** @use HasFactory<LodgeFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => LodgeStatus::class];
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'lodge_user_roles')->withPivot('role_id')->withTimestamps();
    }

    public function features()
    {
        return $this->belongsToMany(Feature::class)->withPivot('enabled')->withTimestamps();
    }

    public function websitePages()
    {
        return $this->hasMany(WebsitePage::class);
    }

    public function mediaAssets()
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function membershipCommunicationPreferences()
    {
        return $this->hasMany(MembershipCommunicationPreference::class);
    }

    public function ownedPersonRelationships()
    {
        return $this->hasMany(PersonRelationship::class, 'owning_lodge_id');
    }

    public function officerAssignments()
    {
        return $this->hasMany(OfficerAssignment::class);
    }

    public function pastMasterTerms()
    {
        return $this->hasMany(PastMasterTerm::class);
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    public function eventCategories()
    {
        return $this->belongsToMany(EventCategory::class)->withTimestamps();
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function eventOccurrences()
    {
        return $this->hasMany(EventOccurrence::class);
    }
}
