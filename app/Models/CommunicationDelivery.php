<?php

namespace App\Models;

use App\Enums\CommunicationDeliveryStatus;
use App\Enums\DeliveryChannel;
use Database\Factories\CommunicationDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunicationDelivery extends Model
{
    /** @use HasFactory<CommunicationDeliveryFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['channel' => DeliveryChannel::class, 'status' => CommunicationDeliveryStatus::class, 'claimed_at' => 'datetime', 'attempted_at' => 'datetime', 'sent_at' => 'datetime', 'prepared_at' => 'datetime', 'mailed_at' => 'datetime'];
    }

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function run()
    {
        return $this->belongsTo(CommunicationDistributionRun::class, 'communication_distribution_run_id');
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function familyNewsletterSubscription()
    {
        return $this->belongsTo(FamilyNewsletterSubscription::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }
}
