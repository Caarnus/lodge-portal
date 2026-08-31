<?php

namespace App\Http\Controllers;

use App\Domain\Ritual\RitualProgress;
use App\Http\Requests\LodgeRitualManagementUpdateRequest;
use App\Models\Lodge;
use App\Models\Membership;
use App\Models\RitualCategory;
use App\Services\LodgeRitualManagementService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LodgeRitualController extends Controller
{
    public function index(Request $request, Lodge $lodge, RitualProgress $progress)
    {
        $this->allowLodge($lodge, 'officers.manage');

        $memberships = Membership::query()
            ->where('lodge_id', $lodge->id)
            ->whereNull('end_date')
            ->whereHas('status', fn($status) => $status->where('key', 'active'))
            ->whereHas('person', fn($person) => $person->where('is_deceased', false)->whereNull('merged_at'))
            ->with([
                'person.user:id,person_id',
                'person.ritualSetting',
                'person.ritualProficiencies.part.category',
                'person.ritualAvailabilities' => fn($availability) => $availability->where('is_enabled', true),
            ])
            ->orderBy('id')
            ->get()
            ->map(function (Membership $membership) use ($progress): array {
                $person = $membership->person;
                $proficiencies = $person->ritualProficiencies;

                return [
                    'membership_id' => $membership->id,
                    'person_id' => $person->id,
                    'display_name' => $person->display_name,
                    'has_linked_account' => $person->user !== null,
                    'visibility_scope' => $person->ritualSetting?->visibility_scope?->value ?? 'hidden',
                    'proficiency_count' => $proficiencies->count(),
                    'learning_count' => $proficiencies->where('status.value', 'learning')->count(),
                    'proficient_count' => $proficiencies->where('status.value', 'proficient')->count(),
                    'completed_count' => $proficiencies->where('performed_for_credit', true)->count(),
                    'willing_count' => $proficiencies->where('willing_to_assist', true)->count(),
                    'current_total' => $progress->currentTotal($person),
                    'proficiencies' => $proficiencies->map(fn($proficiency) => [
                        'ritual_part_id' => $proficiency->ritual_part_id,
                        'status' => $proficiency->status->value,
                        'interested_in_learning' => $proficiency->interested_in_learning,
                        'willing_to_assist' => $proficiency->willing_to_assist,
                        'performed_for_credit' => $proficiency->performed_for_credit,
                        'first_marked_proficient_on' => $proficiency->first_marked_proficient_on?->toDateString(),
                    ])->values(),
                    'availability' => $person->ritualAvailabilities->map(fn($availability) => [
                        'day_of_week' => $availability->day_of_week,
                        'daypart' => $availability->daypart->value,
                    ])->values(),
                ];
            })
            ->values();

        return Inertia::render('ritual/MemberManagement', [
            'lodge' => $lodge->only(['id', 'name', 'number']),
            'memberships' => $memberships,
            'categories' => RitualCategory::query()
                ->with(['parts' => fn($query) => $query->where('is_active', true)->orderBy('sort_order')])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'sort_order']),
        ]);
    }

    public function update(LodgeRitualManagementUpdateRequest $request, Lodge $lodge, Membership $membership, LodgeRitualManagementService $ritual): \Illuminate\Http\RedirectResponse
    {
        $this->allowLodge($lodge, 'officers.manage');
        $ritual->update($lodge, $membership, $request->validated());

        return to_route('lodges.ritual-management.index', $lodge);
    }
}
