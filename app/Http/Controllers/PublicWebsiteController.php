<?php

namespace App\Http\Controllers;

use App\Domain\Directory\DirectoryAccess;
use App\Domain\Newsletters\NewsletterAccess;
use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\LodgeStatus;
use App\Enums\WebsitePageStatus;
use App\Models\EventOccurrence;
use App\Models\GalleryAlbum;
use App\Models\Lodge;
use App\Models\MediaAsset;
use App\Models\Membership;
use App\Models\NewsletterIssue;
use App\Models\OfficerAssignment;
use App\Models\PastMasterTerm;
use App\Models\User;
use App\Models\WebsitePage;
use App\Models\WebsitePageVersion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicWebsiteController extends Controller
{
    public function __construct(
        private readonly DirectoryAccess $directory,
        private readonly NewsletterAccess $newsletters,
    ) {}

    public function home(Lodge $lodge)
    {
        $this->allowPublic($lodge);
        $version = $this->published($lodge)->where('is_home', true)->firstOrFail();

        return $this->render($lodge, $version);
    }

    public function page(Lodge $lodge, string $pageSlug)
    {
        $this->allowPublic($lodge);
        $version = $this->published($lodge)->where('slug', $pageSlug)->firstOrFail();

        return $this->render($lodge, $version);
    }

    public function preview(Request $request, Lodge $lodge, WebsitePage $page)
    {
        abort_unless($page->lodge_id === $lodge->id, 404);
        abort_unless($request->user()->hasLodgePermission($lodge, 'website.manage') || $request->user()->hasLodgePermission($lodge, 'website.publish'), 403);
        $version = $page->draft()->with('sections')->firstOrFail();

        return $this->render($lodge, $version, true);
    }

    public function newsletter(Request $request, Lodge $lodge, NewsletterIssue $issue)
    {
        $this->allowPublic($lodge);
        abort_unless($this->newsletters->canRead($request->user(), $lodge), 403);
        abort_unless($issue->lodge_id === $lodge->id, 404);

        $version = $issue->published()->firstOrFail();
        $navigation = $this->navigation($lodge, $request->user());
        $newsletterIndex = $this->published($lodge)
            ->whereHas('sections', fn ($query) => $query->where('type', 'newsletter_placeholder'))
            ->orderByDesc('is_home')
            ->orderBy('navigation_order')
            ->first();

        return Inertia::render('public/Newsletter', [
            'lodge' => $lodge,
            'issue' => $issue,
            'version' => $version,
            'navigation' => $this->navigationTree($navigation),
            'newsletterIndexUrl' => $newsletterIndex && ! $newsletterIndex->is_home
                ? "/l/{$lodge->slug}/{$newsletterIndex->slug}"
                : "/l/{$lodge->slug}",
        ]);
    }

    private function render(Lodge $lodge, WebsitePageVersion $version, bool $preview = false)
    {
        $version->loadMissing('sections');
        $user = request()->user();
        $memberContent = [
            'directory' => $user instanceof User && $this->directory->canBrowse($user, $lodge),
            'newsletters' => $user instanceof User && $this->newsletters->canRead($user, $lodge),
        ];
        $version->setRelation('sections', $version->sections->reject(
            fn ($section) => ($section->type === 'directory_placeholder' && ! $memberContent['directory'])
                || ($section->type === 'newsletter_placeholder' && ! $memberContent['newsletters']),
        )->values());
        $versions = $this->navigation($lodge, $user);
        $mediaIds = $version->sections->flatMap(function ($section) {
            $ids = [];
            $configuration = $section->configuration;
            array_walk_recursive($configuration, function ($value, $key) use (&$ids) {
                if (($key === 'media_id' || str_ends_with((string) $key, '_media_id')) && is_numeric($value)) {
                    $ids[] = (int) $value;
                }
            });

            return $ids;
        })->unique();
        $officers = collect();
        if ($version->sections->contains('type', 'officers_placeholder')) {
            $officers = OfficerAssignment::query()->where('lodge_id', $lodge->id)->where('is_public', true)
                ->with(['position', 'membership.person'])->get()->sortBy('position.sort_order')->values()->map(fn ($assignment) => [
                    'position' => $assignment->position->name,
                    'name' => $assignment->membership->person->display_name,
                    'email' => $assignment->show_email ? $assignment->membership->person->email : null,
                    'phone' => $assignment->show_phone ? $assignment->membership->person->phone : null,
                ]);
        }
        $pastMasters = collect();
        $events = collect();
        $galleries = collect();
        $newsletters = collect();
        if ($version->sections->contains('type', 'events_placeholder')) {
            $events = EventOccurrence::query()->with('event')->where('lodge_id', $lodge->id)->where('status', EventOccurrenceStatus::Scheduled)->where('starts_at', '>=', now())->whereHas('event', fn ($query) => $query->where('status', EventStatus::Published)->where('visibility', 'public'))->orderBy('starts_at')->limit(20)->get()->map(fn ($occurrence) => ['id' => $occurrence->id, 'title' => $occurrence->title_override ?: $occurrence->event->title, 'starts_at' => $occurrence->starts_at, 'event_category_id' => $occurrence->event->event_category_id]);
        }
        if ($version->sections->contains('type', 'past_masters_placeholder')) {
            $pastMasters = PastMasterTerm::query()
                ->where('lodge_id', $lodge->id)
                ->with('person')
                ->orderByDesc('year')
                ->orderBy('id')
                ->get()
                ->map(fn (PastMasterTerm $term) => [
                    'year' => $term->year,
                    'name' => $term->person->display_name,
                ]);
        }
        if ($version->sections->contains('type', 'gallery_placeholder')) {
            $galleryIds = $version->sections
                ->where('type', 'gallery_placeholder')
                ->flatMap(fn ($section) => $section->configuration['gallery_album_ids'] ?? [])
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
            $galleries = GalleryAlbum::query()->where('lodge_id', $lodge->id)->whereKey($galleryIds)->whereHas('published', fn ($query) => $query->where('visibility', 'public'))
                ->with(['published.photos.mediaAsset'])->get()->map(fn (GalleryAlbum $album) => [
                    'id' => $album->id,
                    'slug' => $album->slug, 'title' => $album->published->title,
                    'cover_photo_id' => $album->published->cover_photo_id ?: $album->published->photos->first()?->id,
                ]);
        }
        if ($memberContent['newsletters'] && $version->sections->contains('type', 'newsletter_placeholder')) {
            $newsletters = NewsletterIssue::query()
                ->where('lodge_id', $lodge->id)
                ->whereHas('published')
                ->with('published')
                ->orderByDesc('id')
                ->get()
                ->map(fn (NewsletterIssue $issue) => [
                    'slug' => $issue->slug,
                    'title' => $issue->published->title,
                    'publication_date' => $issue->published->publication_date?->toDateString(),
                    'has_cover' => $issue->published->cover_media_asset_id !== null,
                ]);
        }

        return Inertia::render('public/Website', [
            'lodge' => $lodge,
            'page' => $version,
            'navigation' => $this->navigationTree($versions),
            'media' => MediaAsset::query()->whereIn('id', $mediaIds)->where('processing_status', 'ready')->where('visibility', 'public')->get()->keyBy('id'),
            'preview' => $preview,
            'officers' => $officers,
            'pastMasters' => $pastMasters,
            'events' => $events,
            'galleries' => $galleries,
            'newsletters' => $newsletters,
            'memberContent' => $memberContent,
        ]);
    }

    private function published(Lodge $lodge)
    {
        return WebsitePageVersion::query()->with('page')->where('lodge_id', $lodge->id)
            ->where('status', WebsitePageStatus::Published)->whereHas('page', fn ($query) => $query->whereNull('deleted_at'));
    }

    private function navigation(Lodge $lodge, ?User $user)
    {
        $query = $this->published($lodge)
            ->where('show_in_navigation', true)
            ->orderBy('navigation_order')
            ->orderBy('title');

        if (! $this->isActiveMember($user)) {
            return $query->where('navigation_visibility', 'public')->get();
        }

        if (! $this->isActiveLodgeMember($user, $lodge)) {
            return $query->whereIn('navigation_visibility', ['public', 'masons'])->get();
        }

        return $query->get();
    }

    private function isActiveMember(?User $user): bool
    {
        $person = $user?->person;

        return $user && $user->approval_status === 'approved' && $user->hasVerifiedEmail() && $person && ! $person->trashed() && ! $person->merged_at && ! $person->is_deceased
            && Membership::query()->where('person_id', $person->id)->whereNull('end_date')->whereHas('status', fn ($query) => $query->where('key', 'active'))->exists();
    }

    private function isActiveLodgeMember(?User $user, Lodge $lodge): bool
    {
        return $user?->person && Membership::query()->where('person_id', $user->person_id)->where('lodge_id', $lodge->id)->whereNull('end_date')->whereHas('status', fn ($query) => $query->where('key', 'active'))->exists();
    }

    private function navigationTree($versions, ?int $parentId = null): array
    {
        return $versions->filter(fn ($version) => $version->navigation_parent_page_id === $parentId)
            ->map(fn ($version) => [
                'title' => $version->title,
                'slug' => $version->slug,
                'is_home' => $version->is_home,
                'is_navigation_container' => $version->is_navigation_container,
                'children' => $this->navigationTree($versions, $version->website_page_id),
            ])->values()->all();
    }

    private function allowPublic(Lodge $lodge): void
    {
        abort_unless($lodge->status === LodgeStatus::Active, 404);
    }
}
