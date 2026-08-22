<?php

namespace App\Models;

use App\Enums\DistributionRunStatus;
use Database\Factories\CommunicationDistributionRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunicationDistributionRun extends Model
{
    /** @use HasFactory<CommunicationDistributionRunFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => DistributionRunStatus::class];
    }

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function newsletterIssueVersion()
    {
        return $this->belongsTo(NewsletterIssueVersion::class);
    }

    public function lodgeCommunication()
    {
        return $this->belongsTo(LodgeCommunication::class);
    }

    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function deliveries()
    {
        return $this->hasMany(CommunicationDelivery::class);
    }
}
