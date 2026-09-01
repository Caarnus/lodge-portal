<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Lodge;
use App\Services\LodgeModuleState;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LodgeModuleController extends Controller
{
    public function edit(Lodge $lodge, LodgeModuleState $states)
    {
        return Inertia::render('platform/LodgeModules', [
            'lodge' => $lodge,
            'modules' => Feature::query()->where('is_active', true)->orderBy('name')->get()
                ->map(fn (Feature $feature) => $this->module($states->resolve($lodge, $feature))),
        ]);
    }

    public function update(Request $request, Lodge $lodge, Feature $feature, LodgeModuleState $states)
    {
        $data = $request->validate(['is_available' => ['required', 'boolean']]);
        abort_unless($feature->is_active, 404);
        $states->setAvailability($request->user(), $lodge, $feature, $data['is_available']);

        return back();
    }

    /** @param array{feature: Feature, is_available: bool, is_enabled: bool, is_effective: bool} $state */
    private function module(array $state): array
    {
        return [
            'id' => $state['feature']->id,
            'key' => $state['feature']->key,
            'name' => $state['feature']->name,
            'description' => $state['feature']->description,
            'is_available' => $state['is_available'],
            'is_enabled' => $state['is_enabled'],
            'is_effective' => $state['is_effective'],
        ];
    }
}
