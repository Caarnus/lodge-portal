<?php

namespace App\Http\Controllers;

use App\Models\Lodge;
use App\Models\WebsitePage;
use App\Models\WebsiteSection;
use App\Services\Audit;
use App\Services\WebsiteSectionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WebsiteSectionController extends Controller
{
    public function store(Request $request, Lodge $lodge, WebsitePage $page, WebsiteSectionCatalog $catalog)
    {
        $this->allow($lodge, $page);
        $draft = $page->draft()->firstOrFail();
        $input = $request->validate([
            'type' => ['required', Rule::in(WebsiteSectionCatalog::TYPES)],
            'configuration' => 'sometimes|required|array',
        ]);
        $platformAdmin = (bool)$request->user()->is_platform_admin;
        $configuration = array_key_exists('configuration', $input)
            ? $catalog->validate($input['type'], $input['configuration'], $lodge, $platformAdmin)
            : $catalog->defaultConfiguration($input['type'], $platformAdmin);
        $section = $draft->sections()->create([
            'lodge_id' => $lodge->id,
            'type' => $input['type'],
            'sort_order' => ((int)$draft->sections()->max('sort_order')) + 1,
            'configuration' => $configuration,
        ]);
        Audit::record('website.section_created', $section, $lodge, null, $section->toArray());

        return back();
    }

    public function update(Request $request, Lodge $lodge, WebsitePage $page, WebsiteSection $section, WebsiteSectionCatalog $catalog)
    {
        $this->allow($lodge, $page, $section);
        $input = $request->validate(['configuration' => 'required|array']);
        $before = $section->toArray();
        $section->update(['configuration' => $catalog->validate($section->type, $input['configuration'], $lodge, (bool)$request->user()->is_platform_admin)]);
        Audit::record('website.section_updated', $section, $lodge, $before, $section->fresh()->toArray());

        return back();
    }

    public function move(Request $request, Lodge $lodge, WebsitePage $page, WebsiteSection $section)
    {
        $this->allow($lodge, $page, $section);
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];
        $operator = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'desc' : 'asc';
        $other = WebsiteSection::query()->where('website_page_version_id', $section->website_page_version_id)
            ->where('sort_order', $operator, $section->sort_order)->orderBy('sort_order', $order)->first();
        if ($other) {
            DB::transaction(function () use ($section, $other) {
                $current = $section->sort_order;
                $otherOrder = $other->sort_order;
                $section->update(['sort_order' => 2_000_000_000]);
                $other->update(['sort_order' => $current]);
                $section->update(['sort_order' => $otherOrder]);
            });
            Audit::record('website.section_reordered', $section, $lodge, null, ['sort_order' => $section->fresh()->sort_order]);
        }

        return back();
    }

    public function destroy(Lodge $lodge, WebsitePage $page, WebsiteSection $section)
    {
        $this->allow($lodge, $page, $section);
        $before = $section->toArray();
        $section->delete();
        Audit::record('website.section_deleted', $section, $lodge, $before, null);

        return back();
    }

    private function allow(Lodge $lodge, WebsitePage $page, ?WebsiteSection $section = null): void
    {
        abort_unless($page->lodge_id === $lodge->id, 404);
        $this->allowLodge($lodge, 'website.manage');
        if ($section) {
            $draftId = $page->draft()->value('id');
            abort_unless($section->lodge_id === $lodge->id && $section->website_page_version_id === $draftId, 404);
        }
    }
}
