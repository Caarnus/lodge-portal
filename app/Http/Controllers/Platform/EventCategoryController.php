<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\EventCategory;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EventCategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('platform/EventCategories', [
            'categories' => EventCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => ['nullable', 'alpha_dash', 'max:100', 'unique:event_categories,key'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $category = EventCategory::create([
            'name' => $data['name'],
            'key' => $data['key'] ?: Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'sort_order' => ((int)EventCategory::query()->max('sort_order')) + 10,
            'is_active' => true,
        ]);
        Audit::record('event.category_created', $category, after: $category->toArray());

        return back()->with('notice', 'Event category created.');
    }

    public function update(Request $request, EventCategory $eventCategory)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'alpha_dash', 'max:100', Rule::unique('event_categories', 'key')->ignore($eventCategory->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);
        $before = $eventCategory->toArray();
        $eventCategory->update($data);
        Audit::record('event.category_updated', $eventCategory, before: $before, after: $eventCategory->fresh()->toArray());

        return back()->with('notice', 'Event category saved.');
    }
}
