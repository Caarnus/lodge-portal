<?php

namespace App\Http\Controllers;

use App\Enums\DistributionRequestStatus;
use App\Models\FamilyNewsletterRequest;
use App\Models\Lodge;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PublicFamilyNewsletterRequestController extends Controller
{
    public function create(Lodge $lodge)
    {
        return Inertia::render('public/NewsletterRequest', ['lodge' => $lodge]);
    }

    public function store(Request $request, Lodge $lodge)
    {
        $data = $request->validate(['requester_name' => 'required|string|max:255', 'requester_email' => 'nullable|email|max:255', 'receives_email' => 'required|boolean', 'receives_print' => 'required|boolean', 'mailing_address_line_1' => 'nullable|string|max:255', 'mailing_address_line_2' => 'nullable|string|max:255', 'mailing_city' => 'nullable|string|max:100', 'mailing_state' => 'nullable|string|size:2', 'mailing_postal_code' => 'nullable|string|max:16', 'claimed_relationship' => 'nullable|string|max:120', 'claimed_related_member_name' => 'nullable|string|max:255', 'website' => 'nullable|max:0']);
        unset($data['website']);
        if (! $data['receives_email'] && ! $data['receives_print']) {
            return back()->withErrors(['channels' => 'Choose a delivery method.']);
        }
        if ($data['receives_email'] && ! $data['requester_email']) {
            return back()->withErrors(['requester_email' => 'Email is required for electronic delivery.']);
        }
        if ($data['receives_print'] && (! filled($data['mailing_address_line_1']) || ! filled($data['mailing_city']) || ! filled($data['mailing_state']) || ! filled($data['mailing_postal_code']))) {
            return back()->withErrors(['mailing_address_line_1' => 'A complete mailing address is required for mailed delivery.']);
        }
        $token = $data['receives_email'] ? Str::random(48) : null;
        $record = FamilyNewsletterRequest::create(array_merge($data, ['lodge_id' => $lodge->id, 'status' => $token ? DistributionRequestStatus::PendingVerification : DistributionRequestStatus::PendingReview, 'email_verification_token_hash' => $token ? hash('sha256', $token) : null, 'email_verification_expires_at' => $token ? now()->addHours(48) : null, 'request_ip' => $request->ip(), 'request_user_agent' => Str::limit((string) $request->userAgent(), 1000)]));
        if ($token) {
            Mail::raw('Verify your newsletter request: '.route('public.newsletters.request.verify.show', [$lodge, $token]), fn ($mail) => $mail->to($record->requester_email)->subject('Verify newsletter request'));
        }
        Audit::record('family_newsletter_request.created', $record, $lodge, null, ['id' => $record->id]);

        return back()->with('notice', 'Your request has been received. Check your email if verification is required.');
    }

    public function verify(Lodge $lodge, string $token)
    {
        return Inertia::render('public/NewsletterRequestVerify', ['lodge' => $lodge, 'token' => $token]);
    }

    public function confirm(Request $request, Lodge $lodge, string $token)
    {
        $record = FamilyNewsletterRequest::query()->where('lodge_id', $lodge->id)->where('email_verification_token_hash', hash('sha256', $token))->first();
        if ($record && $record->status === DistributionRequestStatus::PendingVerification && $record->email_verification_expires_at?->isFuture()) {
            $record->update(['status' => DistributionRequestStatus::PendingReview, 'email_verification_token_hash' => null]);
            Audit::record('family_newsletter_request.verified', $record, $lodge, null, ['id' => $record->id]);
        }

        return redirect()->route('public.newsletters.request.create', $lodge)->with('notice', 'If the request was valid, it is ready for review.');
    }
}
