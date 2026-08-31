<?php

namespace App\Http\Controllers;

use App\Enums\LodgeStatus;
use App\Enums\WebsitePageStatus;
use App\Models\Lodge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PublicContactFormController extends Controller
{
    public function store(Request $request, Lodge $lodge)
    {
        abort_unless($lodge->status === LodgeStatus::Active, 404);
        abort_unless($this->isEnabled($lodge), 404);
        $recipient = $lodge->contact_email ?: $lodge->public_email;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'website' => ['nullable', 'max:0'],
        ]);

        Mail::raw(
            "New contact form submission for {$lodge->name}\n\n"
            . "From: {$data['name']} <{$data['email']}>\n\n"
            . "Message:\n{$data['message']}",
            fn($mail) => $mail
                ->to($recipient)
                ->replyTo($data['email'], $data['name'])
                ->subject("Website contact: {$lodge->name}"),
        );

        return back()->with('notice', 'Thank you. Your message has been sent.');
    }

    private function isEnabled(Lodge $lodge): bool
    {
        return $lodge->websitePages()
            ->whereHas('versions', fn($versions) => $versions
                ->where('status', WebsitePageStatus::Published)
                ->whereHas('sections', fn($sections) => $sections->where('type', 'contact_information')))
            ->with(['published.sections' => fn($sections) => $sections->where('type', 'contact_information')])
            ->get()
            ->flatMap(fn($page) => $page->published?->sections ?? [])
            ->contains(fn($section) => (bool)($section->configuration['show_contact_form'] ?? false));
    }
}
