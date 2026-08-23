<?php

namespace App\Http\Controllers;

use App\Domain\Galleries\MediaExposureService;
use App\Domain\Newsletters\NewsletterAccess;
use App\Models\Lodge;
use App\Models\NewsletterIssue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MemberNewsletterController extends Controller
{
    public function index(Request $request, Lodge $lodge, NewsletterAccess $access)
    {
        abort_unless($access->canRead($request->user(), $lodge), 403);

        return Inertia::render('newsletters/Archive', ['lodge' => $lodge, 'issues' => $lodge->newsletterIssues()->whereHas('published')->with('published')->orderByDesc('id')->get()]);
    }

    public function show(Request $request, Lodge $lodge, NewsletterIssue $issue, NewsletterAccess $access)
    {
        abort_unless($access->canRead($request->user(), $lodge), 403);
        abort_unless($issue->lodge_id === $lodge->id, 404);
        $version = $issue->published()->with('coverMediaAsset')->first();
        abort_unless($version, 404);

        return Inertia::render('newsletters/Show', compact('lodge', 'issue', 'version'));
    }

    public function cover(Request $request, Lodge $lodge, NewsletterIssue $issue, NewsletterAccess $access, MediaExposureService $media)
    {
        abort_unless($access->canRead($request->user(), $lodge), 403);
        abort_unless($issue->lodge_id === $lodge->id, 404);
        $asset = $issue->published()->with('coverMediaAsset')->first()?->coverMediaAsset;
        abort_unless($asset && $asset->lodge_id === $lodge->id, 404);

        return $media->privateDerivativeResponse($asset, true);
    }

    public function document(Request $request, Lodge $lodge, NewsletterIssue $issue, NewsletterAccess $access)
    {
        abort_unless($access->canRead($request->user(), $lodge), 403);
        abort_unless($issue->lodge_id === $lodge->id, 404);
        $document = $issue->published()->with('document')->first()?->document;
        abort_unless($document && $document->lodge_id === $lodge->id && Storage::disk('local')->exists($document->storage_path), 404);

        return Storage::disk('local')->download($document->storage_path, $document->original_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Type' => 'application/pdf']);
    }
}
