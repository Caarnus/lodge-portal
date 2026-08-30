<?php

namespace App\Http\Controllers;

use App\Domain\Events\EventDiscovery;
use App\Models\EventCategory;
use App\Models\Lodge;
use App\Models\LodgeGroup;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RegionalEventController extends Controller
{
    public function index(Request $request, EventDiscovery $discovery)
    {
        $filters = $request->validate([
            'group' => ['nullable', 'string', 'max:100'],
            'lodge' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'visibility' => ['nullable', 'in:public,masons,lodge'],
            'qualification' => ['nullable', 'in:ea,fc,mm,pm'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $protectedViewer = $discovery->isProtectedViewer($request->user());
        $groups = LodgeGroup::query();
        $protectedViewer ? $groups->active() : $groups->discoverable();

        $response = Inertia::render('public/RegionalEvents', [
            'events' => $discovery->paginate($request->user(), $filters),
            'filters' => $filters,
            'filterOptions' => [
                'groups' => $groups->orderBy('name')->get(['id', 'name', 'slug']),
                'lodges' => Lodge::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'number', 'slug']),
                'categories' => EventCategory::query()->where('is_active', true)->orderBy('name')->get(['key', 'name']),
            ],
            'canViewProtectedEvents' => $protectedViewer,
        ]);

        if ($request->user()) {
            return $response->toResponse($request)->header('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
