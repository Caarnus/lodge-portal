<?php

namespace Database\Factories;

use App\Enums\CommunicationDeliveryStatus;
use App\Enums\DeliveryChannel;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationDistributionRun;
use App\Models\FamilyNewsletterSubscription;
use App\Models\NewsletterIssue;
use App\Models\NewsletterIssueVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommunicationDeliveryFactory extends Factory
{
    protected $model = CommunicationDelivery::class;

    public function definition(): array
    {
        $subscription = FamilyNewsletterSubscription::factory()->create();
        $issue = NewsletterIssue::factory()->create(['lodge_id' => $subscription->lodge_id]);
        $version = NewsletterIssueVersion::factory()->create(['lodge_id' => $subscription->lodge_id, 'newsletter_issue_id' => $issue->id]);
        $run = CommunicationDistributionRun::factory()->create(['lodge_id' => $subscription->lodge_id, 'newsletter_issue_version_id' => $version->id]);
        $email = $subscription->recipient->email;

        return [
            'lodge_id' => $subscription->lodge_id,
            'communication_distribution_run_id' => $run->id,
            'channel' => DeliveryChannel::Email,
            'family_newsletter_subscription_id' => $subscription->id,
            'recipient_name' => $subscription->recipient->display_name,
            'recipient_email' => $email,
            'normalized_recipient_email' => mb_strtolower((string) $email),
            'status' => CommunicationDeliveryStatus::Pending,
            'unsubscribe_token_hash' => hash('sha256', fake()->uuid()),
        ];
    }
}
