<?php

namespace App\Http\Controllers;

use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\LodgeStatus;
use App\Enums\WebsitePageStatus;
use App\Models\EventOccurrence;
use App\Models\GalleryAlbum;
use App\Models\Lodge;
use App\Models\MediaAsset;
use App\Models\OfficerAssignment;
use App\Models\PastMasterTerm;
use App\Models\WebsitePage;
use App\Models\WebsitePageVersion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicWebsiteController extends Controller
{
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

    private function render(Lodge $lodge, WebsitePageVersion $version, bool $preview = false)
    {
        $version->loadMissing('sections');
        $version->sections->where('type', 'newsletter_placeholder')->each(function ($section) {
            $section->configuration = ['heading' => 'Member newsletters', 'body' => 'Sign in to view member newsletters.', 'member_only' => true];
        });
        $versions = $this->published($lodge)->where('show_in_navigation', true)->orderBy('navigation_order')->orderBy('title')->get();
        $mediaIds = $version->sections->flatMap(function ($section) {
            $ids = [];
            $configuration = $section->configuration;
            array_walk_recursive($configuration, function ($value, $key) use (&$ids) {
                if (($key === 'media_id' || str_ends_with((string)$key, '_media_id')) && is_numeric($value)) {
                    $ids[] = (int)$value;
                }
            });

            return $ids;
        })->unique();
        $officers = collect();
        if ($version->sections->contains('type', 'officers_placeholder')) {
            $officers = OfficerAssignment::query()->where('lodge_id', $lodge->id)->where('is_public', true)
                ->with(['position', 'membership.person'])->get()->sortBy('position.sort_order')->values()->map(fn($assignment) => [
                    'position' => $assignment->position->name,
                    'name' => $assignment->membership->person->display_name,
                    'email' => $assignment->show_email ? $assignment->membership->person->email : null,
                    'phone' => $assignment->show_phone ? $assignment->membership->person->phone : null,
                ]);
        }
        $pastMasters = collect();
        $events = collect();
        $galleries = collect();
        if ($version->sections->contains('type', 'events_placeholder')) {
            $events = EventOccurrence::query()->with('event')->where('lodge_id', $lodge->id)->where('status', EventOccurrenceStatus::Scheduled)->where('starts_at', '>=', now())->whereHas('event', fn($query) => $query->where('status', EventStatus::Published)->where('visibility', 'public'))->orderBy('starts_at')->limit(20)->get()->map(fn($occurrence) => ['id' => $occurrence->id, 'title' => $occurrence->title_override ?: $occurrence->event->title, 'starts_at' => $occurrence->starts_at, 'event_category_id' => $occurrence->event->event_category_id]);
        }
        if ($version->sections->contains('type', 'past_masters_placeholder')) {
            $pastMasters = PastMasterTerm::query()
                ->where('lodge_id', $lodge->id)
                ->with('person')
                ->orderByDesc('year')
                ->orderBy('id')
                ->get()
                ->map(fn(PastMasterTerm $term) => [
                    'year' => $term->year,
                    'name' => $term->person->display_name,
                ]);
        }
        if ($version->sections->contains('type', 'gallery_placeholder')) {
            $galleries = GalleryAlbum::query()->where('lodge_id', $lodge->id)->whereHas('published', fn($query) => $query->where('visibility', 'public'))
                ->with(['published.photos.mediaAsset'])->orderByDesc('id')->limit(12)->get()->map(fn(GalleryAlbum $album) => [
                    'slug' => $album->slug, 'title' => $album->published->title,
                    'cover_photo_id' => $album->published->cover_photo_id ?: $album->published->photos->first()?->id,
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
        ]);
    }

    private function published(Lodge $lodge)
    {
        return WebsitePageVersion::query()->with('page')->where('lodge_id', $lodge->id)
            ->where('status', WebsitePageStatus::Published)->whereHas('page', fn($query) => $query->whereNull('deleted_at'));
    }

    private function navigationTree($versions, ?int $parentId = null): array
    {
        return $versions->filter(fn($version) => $version->navigation_parent_page_id === $parentId)
            ->map(fn($version) => [
                'title' => $version->title,
                'slug' => $version->slug,
                'is_home' => $version->is_home,
                'children' => $this->navigationTree($versions, $version->website_page_id),
            ])->values()->all();
    }

    private function allowPublic(Lodge $lodge): void
    {
        abort_unless($lodge->status === LodgeStatus::Active, 404);
    }
}
