<?php

namespace Database\Factories;

use App\Enums\DistributionRequestStatus;
use App\Models\FamilyNewsletterRequest;
use App\Models\Lodge;
use Illuminate\Database\Eloquent\Factories\Factory;

class FamilyNewsletterRequestFactory extends Factory
{
    protected $model = FamilyNewsletterRequest::class;

    public function definition(): array
    {
        return [
            'lodge_id' => Lodge::factory(),
            'receives_email' => true,
            'receives_print' => false,
            'requester_name' => fake()->name(),
            'requester_email' => fake()->safeEmail(),
            'claimed_relationship' => 'spouse',
            'claimed_related_member_name' => fake()->name(),
            'status' => DistributionRequestStatus::PendingVerification,
            'email_verification_token_hash' => hash('sha256', fake()->uuid()),
            'email_verification_expires_at' => now()->addHours(48),
        ];
    }
}
