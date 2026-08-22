<?php

namespace App\Models;

use App\Enums\DistributionRequestStatus;
use Database\Factories\FamilyNewsletterRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FamilyNewsletterRequest extends Model
{
    /** @use HasFactory<FamilyNewsletterRequestFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['receives_email' => 'boolean', 'receives_print' => 'boolean', 'status' => DistributionRequestStatus::class, 'email_verification_expires_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function subscription()
    {
        return $this->belongsTo(FamilyNewsletterSubscription::class, 'family_newsletter_subscription_id');
    }
}
