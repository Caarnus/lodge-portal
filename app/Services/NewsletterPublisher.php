<?php

namespace App\Services;

use App\Enums\ContentVersionStatus;
use App\Enums\MediaProcessingStatus;
use App\Models\Lodge;
use App\Models\NewsletterDocument;
use App\Models\NewsletterIssue;
use App\Models\NewsletterIssueVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NewsletterPublisher
{
    public function __construct(private readonly WebsiteHtmlSanitizer $sanitizer)
    {
    }

    public function create(Lodge $lodge, User $user, array $data): NewsletterIssue
    {
        return DB::transaction(function () use ($lodge, $user, $data) {
            $issue = NewsletterIssue::create(['lodge_id' => $lodge->id, 'slug' => $data['slug'], 'created_by' => $user->id]);
            $issue->versions()->create($this->draftData($lodge, $user, $data));
            Audit::record('newsletter.issue_created', $issue, $lodge, null, $issue->fresh('draft')->toArray());

            return $issue;
        });
    }

    private function draftData(Lodge $lodge, User $user, array $data, bool $creating = true): array
    {
        $coverId = $data['cover_media_asset_id'] ?? null;
        if ($coverId && !$lodge->mediaAssets()->whereKey($coverId)->where('processing_status', MediaProcessingStatus::Ready)->exists()) {
            throw ValidationException::withMessages(['cover_media_asset_id' => 'Cover must be a ready asset from this lodge.']);
        }
        $documentId = $data['newsletter_document_id'] ?? null;
        if ($documentId && !NewsletterDocument::query()->whereKey($documentId)->where('lodge_id', $lodge->id)->exists()) {
            throw ValidationException::withMessages(['newsletter_document_id' => 'Document is unavailable.']);
        }

        return array_filter([
            'lodge_id' => $lodge->id, 'status' => ContentVersionStatus::Draft, 'title' => $data['title'],
            'publication_date' => $data['publication_date'] ?? null, 'cover_media_asset_id' => $coverId,
            'body_html' => filled($data['body_html'] ?? null) ? $this->sanitizer->sanitize($data['body_html']) : null,
            'newsletter_document_id' => $documentId, 'created_by' => $creating ? $user->id : null,
        ], fn($value, $key) => $creating || $key !== 'created_by' || $value !== null, ARRAY_FILTER_USE_BOTH);
    }

    public function publish(NewsletterIssue $issue, User $user): NewsletterIssueVersion
    {
        return DB::transaction(function () use ($issue, $user) {
            $issue = NewsletterIssue::query()->lockForUpdate()->findOrFail($issue->id);
            $draft = $issue->draft()->lockForUpdate()->firstOrFail();
            $this->validatePublishable($draft);
            $old = $issue->published()->lockForUpdate()->first();
            $old?->update(['status' => ContentVersionStatus::Archived]);
            $draft->update(['status' => ContentVersionStatus::Published, 'published_at' => now(), 'published_by' => $user->id]);
            Audit::record('newsletter.issue_published', $issue, $issue->lodge, $old?->toArray(), $draft->fresh()->toArray());

            return $draft->fresh();
        });
    }

    private function validatePublishable(NewsletterIssueVersion $draft): void
    {
        if (!filled(strip_tags((string)$draft->body_html)) && !$draft->newsletter_document_id) {
            throw ValidationException::withMessages(['newsletter' => 'A published newsletter needs rich content or a PDF document.']);
        }
        if ($draft->newsletter_document_id && !NewsletterDocument::query()->whereKey($draft->newsletter_document_id)->where('lodge_id', $draft->lodge_id)->exists()) {
            throw ValidationException::withMessages(['newsletter_document_id' => 'Document is unavailable.']);
        }
    }

    public function update(NewsletterIssue $issue, Lodge $lodge, User $user, array $data): NewsletterIssueVersion
    {
        return DB::transaction(function () use ($issue, $lodge, $user, $data) {
            $issue = NewsletterIssue::query()->lockForUpdate()->findOrFail($issue->id);
            $draft = $this->draftFor($issue, $user);
            $before = $draft->toArray();
            $issue->update(['slug' => $data['slug']]);
            $draft->update($this->draftData($lodge, $user, $data, false));
            Audit::record('newsletter.issue_updated', $issue, $lodge, $before, $draft->fresh()->toArray());

            return $draft->fresh();
        });
    }

    public function draftFor(NewsletterIssue $issue, User $user): NewsletterIssueVersion
    {
        if ($draft = $issue->draft()->first()) {
            return $draft;
        }

        return DB::transaction(function () use ($issue, $user) {
            $issue = NewsletterIssue::query()->lockForUpdate()->findOrFail($issue->id);
            if ($draft = $issue->draft()->first()) {
                return $draft;
            }
            $published = $issue->published()->firstOrFail();
            $draft = $published->replicate(['status', 'published_at', 'published_by']);
            $draft->status = ContentVersionStatus::Draft;
            $draft->created_by = $user->id;
            $draft->published_at = null;
            $draft->published_by = null;
            $draft->save();

            return $draft;
        });
    }

    public function unpublish(NewsletterIssue $issue, User $user): void
    {
        DB::transaction(function () use ($issue, $user) {
            $issue = NewsletterIssue::query()->lockForUpdate()->findOrFail($issue->id);
            $published = $issue->published()->lockForUpdate()->firstOrFail();
            $draft = $published->replicate(['status', 'published_at', 'published_by']);
            $draft->status = ContentVersionStatus::Draft;
            $draft->created_by = $user->id;
            $draft->published_at = null;
            $draft->published_by = null;
            $draft->save();
            $published->update(['status' => ContentVersionStatus::Archived]);
            Audit::record('newsletter.issue_unpublished', $issue, $issue->lodge, $published->toArray(), null);
        });
    }
}
