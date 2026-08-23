<?php

namespace App\Http\Controllers;

use App\Models\EventCategory;
use App\Models\Lodge;
use App\Services\Audit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventCategoryController extends Controller
{
    public function edit(Lodge $lodge)
    {
        $this->allow($lodge);

        return Inertia::render('events/Categories', [
            'lodge' => $lodge->only('id', 'name'),
            'categories' => EventCategory::query()->orderBy('sort_order')->get()->map(fn(EventCategory $category) => [
                'id' => $category->id,
                'key' => $category->key,
                'name' => $category->name,
                'description' => $category->description,
                'is_active' => $category->is_active,
                'enabled' => $lodge->eventCategories()->whereKey($category->id)->exists(),
            ]),
        ]);
    }

    public function update(Request $request, Lodge $lodge)
    {
        $this->allow($lodge);
        $validated = $request->validate([
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:event_categories,id'],
        ]);
        $ids = EventCategory::query()->where('is_active', true)->whereIn('id', $validated['category_ids'] ?? [])->pluck('id')->all();
        $before = $lodge->eventCategories()->pluck('event_categories.id')->all();
        $lodge->eventCategories()->sync($ids);
        Audit::record('event.categories.updated', $lodge, $lodge, ['category_ids' => $before], ['category_ids' => $ids]);

        return back()->with('notice', 'Event categories updated.');
    }

    private function allow(Lodge $lodge): void
    {
        abort_unless(request()->user()?->hasLodgePermission($lodge, 'events.manage'), 403);
    }
}
