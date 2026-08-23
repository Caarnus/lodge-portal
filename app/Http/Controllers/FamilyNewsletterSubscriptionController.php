<?php

namespace App\Http\Controllers;

use App\Domain\Newsletters\FamilyNewsletterEligibility;
use App\Enums\DistributionRequestStatus;
use App\Models\FamilyNewsletterRequest;
use App\Models\FamilyNewsletterSubscription;
use App\Models\Lodge;
use App\Models\Person;
use App\Models\PersonRelationship;
use App\Services\Audit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FamilyNewsletterSubscriptionController extends Controller
{
    public function index(Lodge $lodge)
    {
        $this->allowLodge($lodge, 'communications.recipients');

        return Inertia::render('communications/Recipients', ['lodge' => $lodge, 'requests' => $lodge->familyNewsletterRequests()->whereIn('status', ['pending_verification', 'pending_review'])->latest()->get(), 'subscriptions' => $lodge->familyNewsletterSubscriptions()->with(['recipient', 'sponsor', 'relationship.type'])->latest()->get(), 'people' => Person::query()->whereHas('memberships', fn ($q) => $q->where('lodge_id', $lodge->id))->orderBy('name')->get(['id', 'name', 'legal_first_name', 'legal_last_name']), 'relationships' => PersonRelationship::query()->where('owning_lodge_id', $lodge->id)->with('type')->get()]);
    }

    public function approve(Request $request, Lodge $lodge, FamilyNewsletterRequest $familyRequest, FamilyNewsletterEligibility $eligibility)
    {
        $this->allowLodge($lodge, 'communications.recipients');
        abort_unless($familyRequest->lodge_id === $lodge->id && $familyRequest->status === DistributionRequestStatus::PendingReview, 404);
        $data = $request->validate(['recipient_person_id' => 'required|integer', 'sponsoring_person_id' => 'required|integer', 'person_relationship_id' => 'required|integer', 'receives_email' => 'required|boolean', 'receives_print' => 'required|boolean', 'administrative_note' => 'nullable|string|max:2000']);
        $recipient = Person::findOrFail($data['recipient_person_id']);
        $sponsor = Person::findOrFail($data['sponsoring_person_id']);
        $relationship = PersonRelationship::findOrFail($data['person_relationship_id']);
        $eligibility->assertEligible($lodge, $recipient, $sponsor, $relationship, $data['receives_email'], $data['receives_print']);
        $subscription = FamilyNewsletterSubscription::updateOrCreate(['lodge_id' => $lodge->id, 'recipient_person_id' => $recipient->id], array_merge($data, ['status' => 'active', 'consent_source' => 'family_request', 'requested_at' => $familyRequest->created_at, 'approved_by' => $request->user()->id, 'updated_by' => $request->user()->id]));
        $familyRequest->update(['status' => DistributionRequestStatus::Approved, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'family_newsletter_subscription_id' => $subscription->id]);
        Audit::record('family_newsletter_subscription.approved', $subscription, $lodge, null, ['id' => $subscription->id]);

        return back();
    }

    public function reject(Request $request, Lodge $lodge, FamilyNewsletterRequest $familyRequest)
    {
        $this->allowLodge($lodge, 'communications.recipients');
        abort_unless($familyRequest->lodge_id === $lodge->id, 404);
        $familyRequest->update(['status' => DistributionRequestStatus::Rejected, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $request->validate(['review_note' => 'nullable|string|max:2000'])['review_note'] ?? null]);
        Audit::record('family_newsletter_request.rejected', $familyRequest, $lodge, null, ['id' => $familyRequest->id]);

        return back();
    }

    public function update(Request $request, Lodge $lodge, FamilyNewsletterSubscription $subscription, FamilyNewsletterEligibility $eligibility)
    {
        $this->allowLodge($lodge, 'communications.recipients');
        abort_unless($subscription->lodge_id === $lodge->id, 404);
        $data = $request->validate(['receives_email' => 'required|boolean', 'receives_print' => 'required|boolean', 'administrative_note' => 'nullable|string|max:2000']);
        $eligibility->assertEligible($lodge, $subscription->recipient, $subscription->sponsor, $subscription->relationship, $data['receives_email'], $data['receives_print']);
        $subscription->update($data + ['updated_by' => $request->user()->id]);
        Audit::record('family_newsletter_subscription.updated', $subscription, $lodge);

        return back();
    }
}
