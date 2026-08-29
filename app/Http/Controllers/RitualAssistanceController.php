<?php

namespace App\Http\Controllers;

use App\Domain\Ritual\RitualAssistanceAccess;
use App\Enums\LodgeStatus;
use App\Models\Lodge;
use App\Models\Person;
use App\Models\RitualCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RitualAssistanceController extends Controller
{
    public function index(Request $request, Lodge $lodge, RitualAssistanceAccess $access)
    {
        abort_unless($access->canBrowse($request->user(), $lodge), 403);

        $filters = $request->validate([
            'audience' => ['nullable', 'in:own_lodge,participating_lodges'],
            'part' => ['nullable', 'integer', 'exists:ritual_parts,id'],
            'category' => ['nullable', 'integer', 'exists:ritual_categories,id'],
            'degree' => ['nullable', 'integer', 'exists:masonic_degrees,id'],
            'lodge' => ['nullable', 'integer', 'exists:lodges,id'],
            'day_of_week' => ['nullable', 'required_with:daypart', 'integer', 'between:1,7'],
            'daypart' => ['nullable', 'required_with:day_of_week', 'in:morning,afternoon,evening'],
            'query' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:25'],
            'searched' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:name,affiliations,roles,availability'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $searched = $request->boolean('searched');
        $results = $searched ? $access->search($lodge, $filters) : null;
        if ($request->expectsJson()) {
            return response()->json($results)->header('Cache-Control', 'private, no-store');
        }

        return Inertia::render('ritual/Assistance', [
            'requestingLodge' => $lodge->only(['id', 'name', 'number']),
            'filters' => $filters,
            'results' => $results,
            'searched' => $searched,
            'categories' => RitualCategory::query()->with(['parts' => fn ($parts) => $parts->where('is_active', true)->orderBy('sort_order')])->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'masonic_degree_id']),
            'lodges' => Lodge::query()->where('status', LodgeStatus::Active)->orderBy('name')->get(['id', 'name', 'number']),
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, Lodge $lodge, Person $person, RitualAssistanceAccess $access)
    {
        abort_unless($access->canBrowse($request->user(), $lodge), 403);
        $audience = $request->validate(['audience' => ['nullable', 'in:own_lodge,participating_lodges']])['audience'] ?? 'own_lodge';
        $person = $access->findVisible($lodge, $person, $audience);
        abort_unless($person, 404);

        $projection = $access->project($person, [], true);
        if ($request->header('X-Inertia')) {
            return Inertia::render('ritual/AssistanceDetail', [
                'requestingLodge' => $lodge->only(['id', 'name', 'number']),
                'person' => $projection,
                'audience' => $audience,
            ])->toResponse($request)->header('Cache-Control', 'private, no-store');
        }

        return response()->json($projection)->header('Cache-Control', 'private, no-store');
    }
}
