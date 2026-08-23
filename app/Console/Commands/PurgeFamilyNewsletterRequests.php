<?php

namespace App\Console\Commands;

use App\Enums\DistributionRequestStatus;
use App\Models\FamilyNewsletterRequest;
use App\Services\Audit;
use Illuminate\Console\Command;

class PurgeFamilyNewsletterRequests extends Command
{
    protected $signature = 'newsletters:purge-family-requests';

    protected $description = 'Expire unverified family newsletter requests and purge aged rejected or expired request contact data.';

    public function handle(): int
    {
        FamilyNewsletterRequest::query()->where('status', DistributionRequestStatus::PendingVerification)->where('email_verification_expires_at', '<=', now())->each(function (FamilyNewsletterRequest $request) {
            $request->update(['status' => DistributionRequestStatus::Expired, 'email_verification_token_hash' => null]);
            Audit::record('family_newsletter_request.expired', $request, $request->lodge, null, ['id' => $request->id]);
        });
        FamilyNewsletterRequest::query()->whereIn('status', [DistributionRequestStatus::Rejected, DistributionRequestStatus::Expired])->where('updated_at', '<=', now()->subDays(90))->each(function (FamilyNewsletterRequest $request) {
            $request->update(['requester_name' => 'Purged request', 'requester_email' => null, 'mailing_address_line_1' => null, 'mailing_address_line_2' => null, 'mailing_city' => null, 'mailing_state' => null, 'mailing_postal_code' => null, 'claimed_relationship' => null, 'claimed_related_member_name' => null, 'request_ip' => null, 'request_user_agent' => null]);
            Audit::record('family_newsletter_request.purged', $request, $request->lodge, null, ['id' => $request->id]);
        });

        return self::SUCCESS;
    }
}
