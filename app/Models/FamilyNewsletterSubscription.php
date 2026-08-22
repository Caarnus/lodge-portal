<?php

namespace App\Models;

use Database\Factories\FamilyNewsletterSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FamilyNewsletterSubscription extends Model
{
    /** @use HasFactory<FamilyNewsletterSubscriptionFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['receives_email' => 'boolean', 'receives_print' => 'boolean', 'requested_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function recipient()
    {
        return $this->belongsTo(Person::class, 'recipient_person_id');
    }

    public function sponsor()
    {
        return $this->belongsTo(Person::class, 'sponsoring_person_id');
    }

    public function relationship()
    {
        return $this->belongsTo(PersonRelationship::class, 'person_relationship_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function requests()
    {
        return $this->hasMany(FamilyNewsletterRequest::class);
    }

    public function deliveries()
    {
        return $this->hasMany(CommunicationDelivery::class);
    }
}
