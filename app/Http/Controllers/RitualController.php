<?php

namespace App\Http\Controllers;

use App\Domain\Ritual\RitualProgress;
use App\Http\Requests\RitualAvailabilityUpdateRequest;
use App\Http\Requests\RitualProficiencyUpdateRequest;
use App\Http\Requests\RitualSettingUpdateRequest;
use App\Models\RitualCategory;
use App\Models\RitualPart;
use App\Services\RitualSelfService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class RitualController extends Controller
{
    public function index(RitualSelfService $self, RitualProgress $progress)
    {
        $person = $self->personFor(request()->user());
        return Inertia::render('ritual/Index', [
            'categories' => RitualCategory::query()->with(['parts' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])->where('is_active', true)->orderBy('sort_order')->get(),
            'proficiencies' => $person->ritualProficiencies()->get()->keyBy('ritual_part_id'),
            'settings' => $person->ritualSetting ?? ['visibility_scope' => 'hidden', 'public_availability_note' => null],
            'availability' => $person->ritualAvailabilities()->where('is_enabled', true)->get(['day_of_week', 'daypart']),
            'progress' => $progress->projection($person),
        ]);
    }

    public function updatePart(RitualProficiencyUpdateRequest $request, RitualPart $ritualPart, RitualSelfService $self, RitualProgress $progress): RedirectResponse
    {
        abort_unless($ritualPart->is_active && $ritualPart->category()->where('is_active', true)->exists(), 404);
        $progress->updateProficiency($self->personFor($request->user()), $ritualPart, $request->validated());
        return to_route('ritual.index');
    }

    public function updateSettings(RitualSettingUpdateRequest $request, RitualSelfService $self): RedirectResponse { $self->updateSettings($request->user(), $request->validated()); return to_route('ritual.index'); }
    public function updateAvailability(RitualAvailabilityUpdateRequest $request, RitualSelfService $self): RedirectResponse { $self->replaceAvailability($request->user(), $request->validated('windows')); return to_route('ritual.index'); }
}
