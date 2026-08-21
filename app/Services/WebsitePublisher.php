<?php

namespace App\Services;

use App\Enums\WebsitePageStatus;
use App\Models\User;
use App\Models\WebsitePage;
use App\Models\WebsitePageVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WebsitePublisher
{
    public function __construct(private readonly WebsiteSectionCatalog $catalog) {}

    public function publish(WebsitePage $page, User $user): WebsitePageVersion
    {
        return DB::transaction(function () use ($page, $user) {
            $page = WebsitePage::query()->lockForUpdate()->findOrFail($page->id);
            $draft = $page->draft()->with('sections')->firstOrFail();
            $this->validateNavigation($draft);
            foreach ($draft->sections as $section) {
                $this->catalog->validate($section->type, $section->configuration, $page->lodge, true);
            }

            $slugConflict = WebsitePageVersion::query()->where('lodge_id', $page->lodge_id)
                ->where('status', WebsitePageStatus::Published)->where('slug', $draft->slug)
                ->where('website_page_id', '!=', $page->id)->exists();
            if ($slugConflict) {
                throw ValidationException::withMessages(['slug' => 'Another published page uses this slug.']);
            }

            if ($draft->is_home) {
                $homeConflict = WebsitePageVersion::query()->where('lodge_id', $page->lodge_id)
                    ->where('status', WebsitePageStatus::Published)->where('is_home', true)
                    ->where('website_page_id', '!=', $page->id)->exists();
                if ($homeConflict) {
                    throw ValidationException::withMessages(['is_home' => 'This lodge already has a published home page.']);
                }
            }

            $old = $page->published()->first();
            $old?->update(['status' => WebsitePageStatus::Archived]);
            $draft->update([
                'status' => WebsitePageStatus::Published,
                'published_at' => now(),
                'published_by' => $user->id,
            ]);

            Audit::record('website.page_published', $page, $page->lodge, $old?->toArray(), $draft->fresh()->toArray());

            return $draft->fresh('sections');
        });
    }

    public function unpublish(WebsitePage $page): void
    {
        DB::transaction(function () use ($page) {
            $published = $page->published()->lockForUpdate()->firstOrFail();
            if ($published->is_home) {
                throw ValidationException::withMessages(['page' => 'Published home page cannot be unpublished.']);
            }
            $before = $published->toArray();
            $draft = $published->replicate(['status', 'published_at', 'published_by']);
            $draft->status = WebsitePageStatus::Draft;
            $draft->created_by = $published->published_by;
            $draft->published_at = null;
            $draft->published_by = null;
            $draft->save();
            foreach ($published->sections as $section) {
                $draft->sections()->create($section->only(['lodge_id', 'type', 'sort_order', 'configuration']));
            }
            $published->update(['status' => WebsitePageStatus::Archived]);
            Audit::record('website.page_unpublished', $page, $page->lodge, $before, null);
        });
    }

    private function validateNavigation(WebsitePageVersion $draft): void
    {
        $seen = [$draft->website_page_id];
        $parentId = $draft->navigation_parent_page_id;
        while ($parentId) {
            if (in_array($parentId, $seen, true)) {
                throw ValidationException::withMessages(['navigation_parent_page_id' => 'Navigation cannot contain a cycle.']);
            }
            $seen[] = $parentId;
            $parent = WebsitePage::query()->whereKey($parentId)->where('lodge_id', $draft->lodge_id)->first();
            if (! $parent) {
                throw ValidationException::withMessages(['navigation_parent_page_id' => 'Navigation parent is unavailable.']);
            }
            $parentVersion = $parent->published()->first();
            if (! $parentVersion) {
                throw ValidationException::withMessages(['navigation_parent_page_id' => 'Publish the navigation parent before this page.']);
            }
            if ($draft->show_in_navigation && ! $parentVersion->show_in_navigation) {
                throw ValidationException::withMessages(['navigation_parent_page_id' => 'A visible page cannot be nested below a hidden navigation parent.']);
            }
            $parentId = $parentVersion?->navigation_parent_page_id;
        }
    }
}
