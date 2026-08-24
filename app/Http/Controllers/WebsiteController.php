<?php

namespace App\Http\Controllers;

use App\Enums\WebsitePageStatus;
use App\Models\GalleryAlbum;
use App\Models\Lodge;
use App\Models\MediaAsset;
use App\Models\WebsitePage;
use App\Models\WebsitePageVersion;
use App\Services\Audit;
use App\Services\DefaultWebsiteTemplate;
use App\Services\WebsiteDrafts;
use App\Services\WebsitePublisher;
use App\Services\WebsiteSectionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class WebsiteController extends Controller
{
    public function index(Request $request, Lodge $lodge, WebsiteSectionCatalog $catalog)
    {
        $this->allowLodge($lodge, 'website.manage');

        return Inertia::render('website/Index', [
            'lodge' => $lodge,
            'pages' => $lodge->websitePages()->with(['draft', 'published'])->orderBy('id')->get(),
            'deletedPages' => $lodge->websitePages()->onlyTrashed()->with(['versions' => fn ($query) => $query->whereIn('status', ['draft', 'published'])])->orderByDesc('deleted_at')->get(),
            'media' => MediaAsset::query()->where('lodge_id', $lodge->id)->orderByDesc('id')->get(),
            'canPublish' => $request->user()->hasLodgePermission($lodge, 'website.publish'),
            'sectionTypes' => $this->sectionTypes($catalog, (bool) $request->user()->is_platform_admin),
        ]);
    }

    public function store(Request $request, Lodge $lodge)
    {
        $this->allowLodge($lodge, 'website.manage');
        $data = $this->validatePage($request, $lodge);
        if ($data['is_navigation_container']) {
            $data['slug'] = $this->containerSlug();
        }
        $page = DB::transaction(function () use ($data, $lodge, $request) {
            $page = WebsitePage::create(['lodge_id' => $lodge->id]);
            $page->versions()->create($data + [
                'lodge_id' => $lodge->id,
                'status' => WebsitePageStatus::Draft,
                'created_by' => $request->user()->id,
            ]);
            Audit::record('website.page_created', $page, $lodge, null, $data);

            return $page;
        });

        return redirect()->route('lodges.website.pages.edit', [$lodge, $page]);
    }

    public function edit(Request $request, Lodge $lodge, WebsitePage $page, WebsiteDrafts $drafts, WebsiteSectionCatalog $catalog)
    {
        $this->allowPage($lodge, $page, 'website.manage');
        $draft = $drafts->for($page, $request->user());

        return Inertia::render('website/Edit', [
            'lodge' => $lodge,
            'websitePage' => $page,
            'draft' => $draft->load('sections'),
            'parentPages' => $lodge->websitePages()->whereKeyNot($page->id)->with(['draft', 'published'])->get(),
            'media' => MediaAsset::query()->where(fn ($query) => $query->where('lodge_id', $lodge->id)->orWhere('is_platform_shared', true))->orderByDesc('id')->get(),
            'galleries' => $lodge->galleryAlbums()->whereHas('published')->with('published')->orderByDesc('id')->get()->map(fn (GalleryAlbum $album) => ['id' => $album->id, 'title' => $album->published->title]),
            'sectionTypes' => $this->sectionTypes($catalog, (bool) $request->user()->is_platform_admin),
            'canPublish' => $request->user()->hasLodgePermission($lodge, 'website.publish'),
        ]);
    }

    public function update(Request $request, Lodge $lodge, WebsitePage $page)
    {
        $this->allowPage($lodge, $page, 'website.manage');
        $draft = $page->draft()->firstOrFail();
        $before = $draft->toArray();
        $data = $this->validatePage($request, $lodge, $draft);
        if ($data['is_navigation_container']) {
            $data['slug'] = $draft->is_navigation_container ? $draft->slug : $this->containerSlug();
        }
        $draft->update($data);
        Audit::record('website.page_updated', $page, $lodge, $before, $draft->fresh()->toArray());

        $published = $page->published()->first();
        if ($published) {
            $navigation = collect($data)->only(['show_in_navigation', 'is_navigation_container', 'navigation_visibility', 'navigation_order', 'navigation_parent_page_id'])->all();
            $beforeNavigation = $published->only(array_keys($navigation));
            $published->update($navigation);
            Audit::record('website.navigation_updated', $page, $lodge, $beforeNavigation, $published->fresh()->only(array_keys($navigation)));
        }

        return back();
    }

    public function reorderNavigation(Request $request, Lodge $lodge, WebsiteDrafts $drafts)
    {
        $this->allowLodge($lodge, 'website.manage');
        $data = $request->validate([
            'pages' => ['required', 'array'],
            'pages.*.id' => ['required', 'integer', 'distinct'],
            'pages.*.navigation_parent_page_id' => ['nullable', 'integer'],
            'pages.*.navigation_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);
        $pages = $lodge->websitePages()->with('published')->whereIn('id', collect($data['pages'])->pluck('id'))->get()->keyBy('id');
        abort_unless($pages->count() === $lodge->websitePages()->count() && $pages->count() === count($data['pages']), 422);

        $parents = collect($data['pages'])->mapWithKeys(fn (array $item) => [(int) $item['id'] => $item['navigation_parent_page_id'] ? (int) $item['navigation_parent_page_id'] : null]);
        foreach ($parents as $pageId => $parentId) {
            abort_if($parentId === $pageId || ($parentId !== null && ! $parents->has($parentId)), 422);
            if ($pages->get($pageId)->published && $parentId !== null && ! $pages->get($parentId)->published) {
                throw ValidationException::withMessages(['pages' => 'A published page can only be nested under another published page.']);
            }
            $seen = [$pageId];
            while ($parentId !== null) {
                abort_if(in_array($parentId, $seen, true), 422);
                $seen[] = $parentId;
                $parentId = $parents->get($parentId);
            }
        }

        DB::transaction(function () use ($data, $pages, $drafts, $request) {
            foreach ($data['pages'] as $item) {
                $drafts->for($pages->get($item['id']), $request->user())->update([
                    'navigation_parent_page_id' => $item['navigation_parent_page_id'],
                    'navigation_order' => $item['navigation_order'],
                ]);
                $pages->get($item['id'])->published?->update([
                    'navigation_parent_page_id' => $item['navigation_parent_page_id'],
                    'navigation_order' => $item['navigation_order'],
                ]);
            }
        });
        Audit::record('website.navigation_reordered', $lodge, $lodge, null, ['pages' => $data['pages']]);

        return back();
    }

    public function publish(Request $request, Lodge $lodge, WebsitePage $page, WebsitePublisher $publisher)
    {
        $this->allowPage($lodge, $page, 'website.publish');
        $publisher->publish($page, $request->user());

        return redirect()->route('lodges.website.index', $lodge);
    }

    public function unpublish(Lodge $lodge, WebsitePage $page, WebsitePublisher $publisher)
    {
        $this->allowPage($lodge, $page, 'website.publish');
        $publisher->unpublish($page);

        return back();
    }

    public function destroy(Lodge $lodge, WebsitePage $page)
    {
        $this->allowPage($lodge, $page, 'website.manage');
        if ($page->published()->where('is_home', true)->exists()) {
            throw ValidationException::withMessages(['page' => 'Published home page cannot be deleted.']);
        }
        if (WebsitePageVersion::query()->where('lodge_id', $lodge->id)->where('status', WebsitePageStatus::Published)->where('navigation_parent_page_id', $page->id)->exists()) {
            throw ValidationException::withMessages(['page' => 'Remove this page from published navigation before deleting it.']);
        }
        $page->delete();
        Audit::record('website.page_deleted', $page, $lodge);

        return redirect()->route('lodges.website.index', $lodge);
    }

    public function restore(Lodge $lodge, int $pageId)
    {
        $this->allowLodge($lodge, 'website.manage');
        $page = WebsitePage::onlyTrashed()->whereKey($pageId)->where('lodge_id', $lodge->id)->firstOrFail();
        $page->restore();
        Audit::record('website.page_restored', $page, $lodge);

        return back();
    }

    public function applyTemplate(Request $request, Lodge $lodge, DefaultWebsiteTemplate $template)
    {
        $this->allowLodge($lodge, 'website.manage');
        $template->apply($lodge, $request->user());

        return back();
    }

    public function branding(Request $request, Lodge $lodge)
    {
        $this->allowLodge($lodge, 'website.manage');
        $before = $lodge->only(['tag_line', 'primary_color', 'secondary_color', 'logo_path', 'seal_path']);
        $data = $request->validate([
            'tag_line' => 'nullable|string|max:255',
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo_media_id' => 'nullable|integer',
            'seal_media_id' => 'nullable|integer',
        ]);
        foreach (['logo_media_id' => 'logo_path', 'seal_media_id' => 'seal_path'] as $field => $pathField) {
            if ($data[$field] ?? null) {
                $asset = MediaAsset::query()->whereKey($data[$field])->where('lodge_id', $lodge->id)->where('processing_status', 'ready')->first();
                if (! $asset) {
                    throw ValidationException::withMessages([$field => 'Selected media is unavailable or still processing.']);
                }
                $data[$pathField] = $asset->derivative_path;
            }
            unset($data[$field]);
        }
        $lodge->update($data);
        Audit::record('website.branding_updated', $lodge, $lodge, $before, $lodge->fresh()->only(['tag_line', 'primary_color', 'secondary_color', 'logo_path', 'seal_path']));

        return back();
    }

    private function validatePage(Request $request, Lodge $lodge, ?WebsitePageVersion $version = null): array
    {
        return $request->validate([
            'title' => 'required|string|max:150',
            'slug' => [Rule::requiredIf(! $request->boolean('is_navigation_container')), 'nullable', 'alpha_dash', 'max:100', Rule::notIn(['events', 'calendar.ics', 'reservations', 'reminders']), Rule::unique('website_page_versions', 'slug')->where(fn ($query) => $query->where('lodge_id', $lodge->id)->where('status', 'draft'))->ignore($version?->id)],
            'is_home' => ['required', 'boolean', function ($attribute, $value, $fail) use ($lodge, $version) {
                if ($value && WebsitePageVersion::query()->where('lodge_id', $lodge->id)->where('status', 'draft')->where('is_home', true)->when($version, fn ($query) => $query->whereKeyNot($version->id))->exists()) {
                    $fail('This lodge already has a draft home page.');
                }
            }],
            'show_in_navigation' => 'required|boolean',
            'is_navigation_container' => ['required', 'boolean', function ($attribute, $value, $fail) use ($request) {
                if ($value && $request->boolean('is_home')) {
                    $fail('The home page cannot be a navigation container.');
                }
            }],
            'navigation_visibility' => ['nullable', Rule::in(['public', 'masons', 'lodge'])],
            'navigation_order' => 'required|integer|min:0|max:100000',
            'navigation_parent_page_id' => ['nullable', 'integer', Rule::exists('website_pages', 'id')->where(fn ($query) => $query->where('lodge_id', $lodge->id)->whereNull('deleted_at'))],
        ]);
    }

    private function allowPage(Lodge $lodge, WebsitePage $page, string $permission): void
    {
        abort_unless($page->lodge_id === $lodge->id, 404);
        $this->allowLodge($lodge, $permission);
    }

    private function containerSlug(): string
    {
        return 'container-'.Str::uuid();
    }

    private function sectionTypes(WebsiteSectionCatalog $catalog, bool $platformAdmin): array
    {
        $types = collect($catalog->labels());
        if (! $platformAdmin) {
            $types = $types->except('custom_html');
        }

        return $types->all();
    }
}
