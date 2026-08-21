<?php

namespace App\Http\Controllers;

use App\Enums\LodgeStatus;
use App\Enums\WebsitePageStatus;
use App\Models\Lodge;
use App\Models\MediaAsset;
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
        $versions = $this->published($lodge)->where('show_in_navigation', true)->orderBy('navigation_order')->orderBy('title')->get();
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

        return Inertia::render('public/Website', [
            'lodge' => $lodge,
            'page' => $version,
            'navigation' => $this->navigationTree($versions),
            'media' => MediaAsset::query()->whereIn('id', $mediaIds)->where('processing_status', 'ready')->where('visibility', 'public')->get()->keyBy('id'),
            'preview' => $preview,
        ]);
    }

    private function published(Lodge $lodge)
    {
        return WebsitePageVersion::query()->with('page')->where('lodge_id', $lodge->id)
            ->where('status', WebsitePageStatus::Published)->whereHas('page', fn ($query) => $query->whereNull('deleted_at'));
    }

    private function navigationTree($versions, ?int $parentId = null): array
    {
        return $versions->filter(fn ($version) => $version->navigation_parent_page_id === $parentId)
            ->map(fn ($version) => [
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
