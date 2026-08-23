<?php

namespace App\Http\Controllers;

use App\Domain\Newsletters\NewsletterAccess;
use App\Enums\LodgeCommunicationStatus;
use App\Models\Lodge;
use App\Models\LodgeCommunication;
use App\Models\Membership;
use App\Models\PersonRelationship;
use App\Services\Audit;
use App\Services\CommunicationDistributionService;
use App\Services\WebsiteHtmlSanitizer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LodgeCommunicationController extends Controller
{
    public function index(Request $request, Lodge $lodge)
    {
        $this->allowLodge($lodge, 'communications.send');

        return Inertia::render('communications/Index', [
            'lodge' => $lodge,
            'communications' => $lodge->communications()->with('distributionRuns')->latest()->get(),
            ...$this->recipientOptions($lodge),
        ]);
    }

    public function store(Request $request, Lodge $lodge, WebsiteHtmlSanitizer $sanitizer, CommunicationDistributionService $distributions)
    {
        $this->allowLodge($lodge, 'communications.send');
        $data = $this->data($request, $sanitizer);
        $communication = LodgeCommunication::create($data + ['lodge_id' => $lodge->id, 'status' => LodgeCommunicationStatus::Draft, 'created_by' => $request->user()->id, 'last_edited_by' => $request->user()->id]);
        Audit::record('lodge_communication.created', $communication, $lodge);
        if ($request->boolean('send_now')) {
            $communication->update(['status' => LodgeCommunicationStatus::Sending, 'send_requested_at' => now(), 'sent_by' => $request->user()->id]);
            $distributions->general($lodge, $communication, $request->user());

            return redirect()->route('lodges.communications.index', $lodge);
        }

        return redirect()->route('lodges.communications.index', $lodge);
    }

    public function update(Request $request, Lodge $lodge, LodgeCommunication $communication, WebsiteHtmlSanitizer $sanitizer)
    {
        $this->allow($lodge, $communication);
        abort_unless($communication->status === LodgeCommunicationStatus::Draft, 422);
        $before = $communication->only(['subject']);
        $communication->update($this->data($request, $sanitizer) + ['last_edited_by' => $request->user()->id]);
        Audit::record('lodge_communication.updated', $communication, $lodge, $before, ['subject' => $communication->subject]);

        return back();
    }

    public function send(Request $request, Lodge $lodge, LodgeCommunication $communication, CommunicationDistributionService $distributions)
    {
        $this->allow($lodge, $communication);
        abort_unless($communication->status === LodgeCommunicationStatus::Draft, 422);
        $communication->update(['status' => LodgeCommunicationStatus::Sending, 'send_requested_at' => now(), 'sent_by' => $request->user()->id]);
        $distributions->general($lodge, $communication, $request->user());

        return redirect()->route('lodges.communications.index', $lodge);
    }

    public function duplicate(Request $request, Lodge $lodge, LodgeCommunication $communication)
    {
        $this->allow($lodge, $communication);
        $copy = $communication->replicate(['status', 'send_requested_at', 'sent_at', 'sent_by']);
        $copy->status = LodgeCommunicationStatus::Draft;
        $copy->created_by = $request->user()->id;
        $copy->last_edited_by = $request->user()->id;
        $copy->save();

        return redirect()->route('lodges.communications.index', $lodge);
    }

    public function archive(Request $request, Lodge $lodge, NewsletterAccess $access)
    {
        abort_unless($access->canRead($request->user(), $lodge), 403);

        return Inertia::render('communications/Archive', ['lodge' => $lodge, 'communications' => $lodge->communications()->where('status', LodgeCommunicationStatus::Sent)->latest('sent_at')->get(['id', 'subject', 'body_html', 'sent_at'])]);
    }

    public function show(Request $request, Lodge $lodge, LodgeCommunication $communication, NewsletterAccess $access)
    {
        abort_unless($access->canRead($request->user(), $lodge), 403);
        abort_unless($communication->lodge_id === $lodge->id && $communication->status === LodgeCommunicationStatus::Sent, 404);

        return Inertia::render('communications/Show', compact('lodge', 'communication'));
    }

    private function data(Request $request, WebsiteHtmlSanitizer $sanitizer): array
    {
        $data = $request->validate(['subject' => 'required|string|max:255', 'body_html' => 'required|string|max:100000', 'audience_mode' => 'nullable|in:all,filtered,selected', 'degree_keys' => 'array', 'degree_keys.*' => 'string|max:64', 'membership_status_keys' => 'array', 'membership_status_keys.*' => 'string|max:64', 'membership_ids' => 'array', 'membership_ids.*' => 'integer', 'relation_person_ids' => 'array', 'relation_person_ids.*' => 'integer']);

        return ['subject' => $data['subject'], 'body_html' => $sanitizer->sanitize($data['body_html']), 'audience_mode' => $data['audience_mode'] ?? 'all', 'degree_keys' => $data['degree_keys'] ?? [], 'membership_status_keys' => $data['membership_status_keys'] ?? [], 'membership_ids' => $data['membership_ids'] ?? [], 'relation_person_ids' => $data['relation_person_ids'] ?? []];
    }

    private function allow(Lodge $lodge, LodgeCommunication $communication): void
    {
        abort_unless($communication->lodge_id === $lodge->id, 404);
        $this->allowLodge($lodge, 'communications.send');
    }

    private function recipientOptions(Lodge $lodge): array
    {
        $memberships = Membership::query()->with(['person', 'degree', 'status'])->where('lodge_id', $lodge->id)->whereNull('end_date')->get();
        $relationships = PersonRelationship::query()->with(['personOne', 'personTwo', 'type'])->where('owning_lodge_id', $lodge->id)->get();
        $relations = $relationships->map(fn (PersonRelationship $relation) => ['person_id' => $relation->person_one_id, 'name' => $relation->personOne->display_name, 'related_to' => $relation->personTwo->display_name, 'type' => $relation->type->name])
            ->merge($relationships->map(fn (PersonRelationship $relation) => ['person_id' => $relation->person_two_id, 'name' => $relation->personTwo->display_name, 'related_to' => $relation->personOne->display_name, 'type' => $relation->type->name]))
            ->unique('person_id')->values();

        return compact('memberships', 'relations');
    }
}
