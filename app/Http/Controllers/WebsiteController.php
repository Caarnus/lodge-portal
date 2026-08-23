<?php

namespace App\Http\Controllers;

use App\Enums\WebsitePageStatus;
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
            'deletedPages' => $lodge->websitePages()->onlyTrashed()->with(['versions' => fn($query) => $query->whereIn('status', ['draft', 'published'])])->orderByDesc('deleted_at')->get(),
            'media' => MediaAsset::query()->where('lodge_id', $lodge->id)->orderByDesc('id')->get(),
            'canPublish' => $request->user()->hasLodgePermission($lodge, 'website.publish'),
            'sectionTypes' => $this->sectionTypes($catalog, (bool)$request->user()->is_platform_admin),
        ]);
    }

    public function store(Request $request, Lodge $lodge)
    {
        $this->allowLodge($lodge, 'website.manage');
        $data = $this->validatePage($request, $lodge);
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
            'media' => MediaAsset::query()->where(fn($query) => $query->where('lodge_id', $lodge->id)->orWhere('is_platform_shared', true))->orderByDesc('id')->get(),
            'sectionTypes' => $this->sectionTypes($catalog, (bool)$request->user()->is_platform_admin),
            'canPublish' => $request->user()->hasLodgePermission($lodge, 'website.publish'),
        ]);
    }

    public function update(Request $request, Lodge $lodge, WebsitePage $page)
    {
        $this->allowPage($lodge, $page, 'website.manage');
        $draft = $page->draft()->firstOrFail();
        $before = $draft->toArray();
        $draft->update($this->validatePage($request, $lodge, $draft));
        Audit::record('website.page_updated', $page, $lodge, $before, $draft->fresh()->toArray());

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
                if (!$asset) {
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
            'slug' => ['required', 'alpha_dash', 'max:100', Rule::notIn(['events', 'calendar.ics', 'reservations', 'reminders']), Rule::unique('website_page_versions', 'slug')->where(fn($query) => $query->where('lodge_id', $lodge->id)->where('status', 'draft'))->ignore($version?->id)],
            'is_home' => ['required', 'boolean', function ($attribute, $value, $fail) use ($lodge, $version) {
                if ($value && WebsitePageVersion::query()->where('lodge_id', $lodge->id)->where('status', 'draft')->where('is_home', true)->when($version, fn($query) => $query->whereKeyNot($version->id))->exists()) {
                    $fail('This lodge already has a draft home page.');
                }
            }],
            'show_in_navigation' => 'required|boolean',
            'navigation_order' => 'required|integer|min:0|max:100000',
            'navigation_parent_page_id' => ['nullable', 'integer', Rule::exists('website_pages', 'id')->where(fn($query) => $query->where('lodge_id', $lodge->id)->whereNull('deleted_at'))],
        ]);
    }

    private function allowPage(Lodge $lodge, WebsitePage $page, string $permission): void
    {
        abort_unless($page->lodge_id === $lodge->id, 404);
        $this->allowLodge($lodge, $permission);
    }

    private function sectionTypes(WebsiteSectionCatalog $catalog, bool $platformAdmin): array
    {
        $types = collect($catalog->labels());
        if (!$platformAdmin) {
            $types = $types->except('custom_html');
        }

        return $types->all();
    }
}
