<?php

namespace App\Services;

use App\Domain\Newsletters\FamilyNewsletterEligibility;
use App\Enums\CommunicationDeliveryStatus;
use App\Enums\DeliveryChannel;
use App\Enums\DistributionRunStatus;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationDistributionRun;
use App\Models\FamilyNewsletterSubscription;
use App\Models\Lodge;
use App\Models\LodgeCommunication;
use App\Models\Membership;
use App\Models\NewsletterIssueVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommunicationDistributionService
{
    public function __construct(private readonly FamilyNewsletterEligibility $family)
    {
    }

    public function newsletter(Lodge $lodge, NewsletterIssueVersion $version, User $actor, bool $email = true, bool $postal = false): CommunicationDistributionRun
    {
        return $this->create($lodge, $actor, 'newsletter', $version, null, $email, $postal);
    }

    public function general(Lodge $lodge, LodgeCommunication $communication, User $actor): CommunicationDistributionRun
    {
        return $this->create($lodge, $actor, 'general_message', null, $communication, true, false);
    }

    private function create(Lodge $lodge, User $actor, string $kind, ?NewsletterIssueVersion $version, ?LodgeCommunication $communication, bool $email, bool $postal): CommunicationDistributionRun
    {
        if (!$email && !$postal) {
            throw ValidationException::withMessages(['channels' => 'Choose at least one distribution channel.']);
        }

        return DB::transaction(function () use ($lodge, $actor, $kind, $version, $communication, $email, $postal) {
            $run = CommunicationDistributionRun::create(['lodge_id' => $lodge->id, 'kind' => $kind, 'newsletter_issue_version_id' => $version?->id, 'lodge_communication_id' => $communication?->id, 'status' => DistributionRunStatus::Preparing, 'idempotency_key' => Str::uuid(), 'initiated_by' => $actor->id]);
            $seenEmails = [];
            foreach ($this->members($lodge) as $membership) {
                if ($email && ($emailAddress = $membership->person->email) && !isset($seenEmails[strtolower($emailAddress)])) {
                    $this->delivery($run, $membership, null, DeliveryChannel::Email, $membership->person->display_name, $emailAddress);
                    $seenEmails[strtolower($emailAddress)] = true;
                }
                if ($postal && $membership->communicationPreference?->receives_print_newsletter && $this->addressComplete($membership->person)) {
                    $this->delivery($run, $membership, null, DeliveryChannel::Postal, $membership->person->display_name);
                }
            }
            if ($kind === 'newsletter') {
                foreach ($lodge->familyNewsletterSubscriptions()->with(['recipient', 'sponsor', 'relationship.type'])->where('status', 'active')->get() as $subscription) {
                    if ($this->family->eligible($subscription)) {
                        if ($email && $subscription->receives_email && !isset($seenEmails[strtolower($subscription->recipient->email)])) {
                            $this->delivery($run, null, $subscription, DeliveryChannel::Email, $subscription->recipient->display_name, $subscription->recipient->email);
                            $seenEmails[strtolower($subscription->recipient->email)] = true;
                        }
                        if ($postal && $subscription->receives_print) {
                            $this->delivery($run, null, $subscription, DeliveryChannel::Postal, $subscription->recipient->display_name);
                        }
                    }
                }
            }
            $run->update(['status' => DistributionRunStatus::Ready, 'email_recipient_count' => $run->deliveries()->where('channel', DeliveryChannel::Email)->count(), 'postal_recipient_count' => $run->deliveries()->where('channel', DeliveryChannel::Postal)->count()]);
            Audit::record('communication_distribution.created', $run, $lodge, null, ['id' => $run->id, 'kind' => $kind]);

            return $run;
        });
    }

    public function canSend(CommunicationDelivery $delivery): bool
    {
        $delivery->loadMissing(['run.lodge', 'membership.person.communicationPreference', 'familyNewsletterSubscription.recipient', 'familyNewsletterSubscription.sponsor', 'familyNewsletterSubscription.relationship.type']);
        if ($delivery->run->lodge->status->value !== 'active' || $delivery->channel !== DeliveryChannel::Email) {
            return false;
        }
        if ($delivery->membership) {
            $m = $delivery->membership;

            return $m->isActive() && !$m->person->trashed() && !$m->person->merged_at && !$m->person->is_deceased && ($m->communicationPreference?->receives_lodge_email ?? true) && strtolower((string)$m->person->email) === $delivery->normalized_recipient_email;
        }
        $subscription = $delivery->familyNewsletterSubscription;

        return $subscription && $subscription->receives_email && strtolower((string)$subscription->recipient->email) === $delivery->normalized_recipient_email && $this->family->eligible($subscription);
    }

    private function members(Lodge $lodge)
    {
        return Membership::query()->with(['person', 'communicationPreference'])->where('lodge_id', $lodge->id)->whereNull('end_date')->whereHas('status', fn($q) => $q->where('key', 'active'))->whereHas('person', fn($q) => $q->whereNull('deleted_at')->whereNull('merged_at')->where('is_deceased', false))->get()->filter(fn($m) => $m->communicationPreference?->receives_lodge_email ?? true);
    }

    private function delivery(CommunicationDistributionRun $run, ?Membership $membership, ?FamilyNewsletterSubscription $subscription, DeliveryChannel $channel, string $name, ?string $email = null): void
    {
        $person = $membership?->person ?? $subscription->recipient;
        $run->deliveries()->create(['lodge_id' => $run->lodge_id, 'channel' => $channel, 'membership_id' => $membership?->id, 'family_newsletter_subscription_id' => $subscription?->id, 'recipient_name' => $name, 'recipient_email' => $email, 'normalized_recipient_email' => $email ? strtolower($email) : null, 'mailing_address_line_1' => $channel === DeliveryChannel::Postal ? $person->mailing_address_line_1 : null, 'mailing_address_line_2' => $channel === DeliveryChannel::Postal ? $person->mailing_address_line_2 : null, 'mailing_city' => $channel === DeliveryChannel::Postal ? $person->mailing_city : null, 'mailing_state' => $channel === DeliveryChannel::Postal ? $person->mailing_state : null, 'mailing_postal_code' => $channel === DeliveryChannel::Postal ? $person->mailing_postal_code : null, 'status' => CommunicationDeliveryStatus::Pending]);
    }

    private function addressComplete($person): bool
    {
        return filled($person->mailing_address_line_1) && filled($person->mailing_city) && filled($person->mailing_state) && filled($person->mailing_postal_code);
    }
}
