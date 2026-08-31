<?php

namespace App\Domain\Newsletters;

use App\Models\FamilyNewsletterSubscription;
use App\Models\Lodge;
use App\Models\Membership;
use App\Models\Person;
use App\Models\PersonRelationship;
use Illuminate\Validation\ValidationException;

class FamilyNewsletterEligibility
{
    private const RELATIONSHIP_KEYS = ['spouse', 'child', 'parent', 'widow_widower', 'guardian'];

    public function eligible(FamilyNewsletterSubscription $subscription): bool
    {
        try {
            $this->assertEligible($subscription->lodge, $subscription->recipient, $subscription->sponsor, $subscription->relationship, $subscription->receives_email, $subscription->receives_print);

            return $subscription->status === 'active';
        } catch (ValidationException) {
            return false;
        }
    }

    public function assertEligible(Lodge $lodge, Person $recipient, Person $sponsor, PersonRelationship $relationship, bool $email, bool $print): void
    {
        if (!$email && !$print) {
            throw ValidationException::withMessages(['channels' => 'Choose email, mailed newsletter, or both.']);
        }
        if ($recipient->id === $sponsor->id || $recipient->trashed() || $recipient->merged_at || $recipient->is_deceased) {
            throw ValidationException::withMessages(['recipient_person_id' => 'Recipient is not eligible.']);
        }
        if (!$this->relationshipConnects($relationship, $recipient, $sponsor) || !$relationship->type?->is_active || !in_array($relationship->type->key, self::RELATIONSHIP_KEYS, true)) {
            throw ValidationException::withMessages(['person_relationship_id' => 'Relationship is not eligible for a family subscription.']);
        }
        if (!$this->sponsorHasQualifyingMembership($lodge, $sponsor)) {
            throw ValidationException::withMessages(['sponsoring_person_id' => 'Sponsor has no qualifying lodge membership.']);
        }
        if (Membership::query()->where('lodge_id', $lodge->id)->where('person_id', $recipient->id)->whereNull('end_date')->whereHas('status', fn($q) => $q->where('key', 'active'))->exists()) {
            throw ValidationException::withMessages(['recipient_person_id' => 'Current lodge members use membership preferences.']);
        }
        if ($email && !filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['receives_email' => 'Recipient needs a valid email address.']);
        }
        if ($print && (!filled($recipient->mailing_address_line_1) || !filled($recipient->mailing_city) || !filled($recipient->mailing_state) || !filled($recipient->mailing_postal_code))) {
            throw ValidationException::withMessages(['receives_print' => 'Recipient needs a complete mailing address.']);
        }
    }

    private function relationshipConnects(PersonRelationship $relationship, Person $recipient, Person $sponsor): bool
    {
        return in_array($recipient->id, [$relationship->person_one_id, $relationship->person_two_id], true) && in_array($sponsor->id, [$relationship->person_one_id, $relationship->person_two_id], true);
    }

    private function sponsorHasQualifyingMembership(Lodge $lodge, Person $sponsor): bool
    {
        return Membership::query()->where('lodge_id', $lodge->id)->where('person_id', $sponsor->id)->whereHas('status', fn($q) => $q->where(function ($s) {
            $s->where('key', 'deceased')->orWhere(fn($a) => $a->where('key', 'active')->whereNull('end_date'));
        }))->exists();
    }
}
