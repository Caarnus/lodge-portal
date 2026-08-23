<?php

namespace App\Http\Controllers;

use App\Http\Requests\OfficerAssignmentRequest;
use App\Models\Lodge;
use App\Models\Membership;
use App\Models\OfficerAssignment;
use App\Models\OfficerPosition;
use App\Services\Audit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OfficerController extends Controller
{
    public function index(Request $request, Lodge $lodge)
    {
        $this->allowLodge($lodge, 'officers.manage');

        return Inertia::render('officers/Index', [
            'lodge' => $lodge,
            'assignments' => OfficerAssignment::query()->where('lodge_id', $lodge->id)
                ->with(['membership.person.user:id,person_id', 'position'])->get()->sortBy('position.sort_order')->values(),
            'memberships' => Membership::query()->where('lodge_id', $lodge->id)->whereNull('end_date')
                ->with(['person:id,name,legal_first_name,legal_last_name,preferred_name', 'status'])->orderBy('id')->get(),
            'positions' => OfficerPosition::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(OfficerAssignmentRequest $request, Lodge $lodge)
    {
        $this->allowLodge($lodge, 'officers.manage');
        $membership = Membership::query()->where('lodge_id', $lodge->id)->findOrFail($request->integer('membership_id'));
        $assignment = OfficerAssignment::query()->where('lodge_id', $lodge->id)
            ->where('officer_position_id', $request->integer('officer_position_id'))->first();
        $before = $assignment?->toArray();
        $assignment ??= new OfficerAssignment(['lodge_id' => $lodge->id]);
        $assignment->fill($request->validated() + ['membership_id' => $membership->id])->save();
        Audit::record($before ? 'officer.updated' : 'officer.created', $assignment, $lodge, $before, $assignment->toArray());

        return back()->with('officer_role_prompt', $this->rolePrompt($lodge, $membership, 'assign'));
    }

    public function update(OfficerAssignmentRequest $request, Lodge $lodge, OfficerAssignment $officer)
    {
        $this->allow($lodge, $officer);
        $membership = Membership::query()->where('lodge_id', $lodge->id)->findOrFail($request->integer('membership_id'));
        $before = $officer->toArray();
        $officer->update($request->validated() + ['membership_id' => $membership->id]);
        Audit::record('officer.updated', $officer, $lodge, $before, $officer->fresh()->toArray());

        return back()->with('officer_role_prompt', $this->rolePrompt($lodge, $membership, 'assign', $officer->id));
    }

    public function destroy(Request $request, Lodge $lodge, OfficerAssignment $officer)
    {
        $this->allow($lodge, $officer);
        $membership = $officer->membership;
        $before = $officer->toArray();
        $officer->delete();
        Audit::record('officer.removed', $officer, $lodge, $before);

        return back()->with('officer_role_prompt', $this->rolePrompt($lodge, $membership, 'remove', $officer->id));
    }

    private function allow(Lodge $lodge, OfficerAssignment $officer): void
    {
        abort_unless($officer->lodge_id === $lodge->id, 404);
        $this->allowLodge($lodge, 'officers.manage');
    }

    private function rolePrompt(Lodge $lodge, Membership $membership, string $action, ?int $excludeAssignmentId = null): array
    {
        $user = $membership->person->user;
        $otherAssignments = $membership->officerAssignments()->where('lodge_id', $lodge->id);
        if ($excludeAssignmentId !== null) {
            $otherAssignments->whereKeyNot($excludeAssignmentId);
        }
        $otherCurrent = $otherAssignments->exists();

        return ['action' => $action, 'person_name' => $membership->person->display_name, 'user_id' => $user?->id,
            'has_linked_user' => (bool)$user, 'has_other_current_assignment' => $otherCurrent];
    }
}
