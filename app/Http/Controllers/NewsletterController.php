<?php

namespace App\Http\Controllers;

use App\Models\Lodge;
use App\Models\NewsletterIssue;
use App\Services\Audit;
use App\Services\CommunicationDistributionService;
use App\Services\NewsletterPublisher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class NewsletterController extends Controller
{
    public function index(Request $request, Lodge $lodge)
    {
        $this->allowLodge($lodge, 'newsletters.manage');

        return Inertia::render('newsletters/Index', [
            'lodge' => $lodge,
            'issues' => $lodge->newsletterIssues()->with(['draft', 'published'])->orderByDesc('id')->get(),
            'deletedIssues' => $lodge->newsletterIssues()->onlyTrashed()->with('versions')->orderByDesc('deleted_at')->get(),
            'documents' => $lodge->newsletterDocuments()->orderByDesc('id')->get(),
            'media' => $lodge->mediaAssets()->where('processing_status', 'ready')->orderByDesc('id')->get(),
            'canPublish' => $request->user()->hasLodgePermission($lodge, 'newsletters.publish'),
        ]);
    }

    public function store(Request $request, Lodge $lodge, NewsletterPublisher $publisher)
    {
        $this->allowLodge($lodge, 'newsletters.manage');
        $issue = $publisher->create($lodge, $request->user(), $this->validateIssue($request, $lodge));

        return redirect()->route('lodges.newsletters.index', $lodge);
    }

    private function validateIssue(Request $request, Lodge $lodge, ?NewsletterIssue $issue = null): array
    {
        return $request->validate([
            'title' => 'required|string|max:255', 'slug' => ['required', 'alpha_dash', 'max:160', Rule::unique('newsletter_issues')->where(fn($query) => $query->where('lodge_id', $lodge->id)->whereNull('deleted_at'))->ignore($issue?->id)],
            'publication_date' => 'nullable|date', 'body_html' => 'nullable|string|max:100000',
            'cover_media_asset_id' => 'nullable|integer', 'newsletter_document_id' => 'nullable|integer',
        ]);
    }

    public function edit(Request $request, Lodge $lodge, NewsletterIssue $issue, NewsletterPublisher $publisher)
    {
        $this->allowIssue($lodge, $issue, 'newsletters.manage');

        return Inertia::render('newsletters/Edit', [
            'lodge' => $lodge, 'issue' => $issue,
            'draft' => $publisher->draftFor($issue, $request->user()),
            'documents' => $lodge->newsletterDocuments()->orderByDesc('id')->get(),
            'media' => $lodge->mediaAssets()->where('processing_status', 'ready')->orderByDesc('id')->get(),
            'canPublish' => $request->user()->hasLodgePermission($lodge, 'newsletters.publish'),
        ]);
    }

    private function allowIssue(Lodge $lodge, NewsletterIssue $issue, string $permission): void
    {
        abort_unless($issue->lodge_id === $lodge->id, 404);
        $this->allowLodge($lodge, $permission);
    }

    public function update(Request $request, Lodge $lodge, NewsletterIssue $issue, NewsletterPublisher $publisher)
    {
        $this->allowIssue($lodge, $issue, 'newsletters.manage');
        $publisher->update($issue, $lodge, $request->user(), $this->validateIssue($request, $lodge, $issue));

        return back();
    }

    public function preview(Request $request, Lodge $lodge, NewsletterIssue $issue, NewsletterPublisher $publisher)
    {
        $this->allowIssue($lodge, $issue, 'newsletters.manage');

        return Inertia::render('newsletters/Preview', ['lodge' => $lodge, 'issue' => $issue, 'version' => $publisher->draftFor($issue, $request->user())->load('coverMediaAsset')]);
    }

    public function publish(Request $request, Lodge $lodge, NewsletterIssue $issue, NewsletterPublisher $publisher)
    {
        $this->allowIssue($lodge, $issue, 'newsletters.publish');
        $publisher->publish($issue, $request->user());

        return redirect()->route('lodges.newsletters.index', $lodge);
    }

    public function unpublish(Request $request, Lodge $lodge, NewsletterIssue $issue, NewsletterPublisher $publisher)
    {
        $this->allowIssue($lodge, $issue, 'newsletters.publish');
        $publisher->unpublish($issue, $request->user());

        return back();
    }

    public function distribute(Request $request, Lodge $lodge, NewsletterIssue $issue, CommunicationDistributionService $distributions)
    {
        $this->allowIssue($lodge, $issue, 'communications.send');
        $version = $issue->published()->firstOrFail();
        $data = $request->validate(['send_email' => ['required', 'boolean'], 'prepare_postal' => ['required', 'boolean']]);
        $distributions->newsletter($lodge, $version, $request->user(), $data['send_email'], $data['prepare_postal']);

        return back();
    }

    public function destroy(Lodge $lodge, NewsletterIssue $issue)
    {
        $this->allowIssue($lodge, $issue, 'newsletters.manage');
        abort_if($issue->published()->exists(), 422, 'Unpublish before deleting this issue.');
        $issue->delete();
        Audit::record('newsletter.issue_deleted', $issue, $lodge);

        return redirect()->route('lodges.newsletters.index', $lodge);
    }

    public function restore(Lodge $lodge, int $issueId)
    {
        $this->allowLodge($lodge, 'newsletters.manage');
        $issue = NewsletterIssue::onlyTrashed()->where('lodge_id', $lodge->id)->findOrFail($issueId);
        $issue->restore();
        Audit::record('newsletter.issue_restored', $issue, $lodge);

        return back();
    }
}
