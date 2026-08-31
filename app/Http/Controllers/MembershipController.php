<?php

namespace App\Http\Controllers;

use App\Http\Requests\MembershipRequest;
use App\Models\Lodge;
use App\Models\Membership;
use App\Models\PastMasterTerm;
use App\Services\Audit;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    public function end(Request $request, Lodge $lodge, Membership $membership)
    {
        $this->allow($lodge, $membership);
        $data = $request->validate(['end_date' => 'required|date|before_or_equal:today']);
        $before = $membership->toArray();
        $membership->update($data);
        Audit::record('membership.ended', $membership, $lodge, $before, $membership->fresh()->toArray());

        return back();
    }

    private function allow(Lodge $lodge, Membership $membership): void
    {
        abort_unless($membership->lodge_id === $lodge->id, 404);
        $this->allowLodge($lodge, 'memberships.manage');
    }

    public function update(MembershipRequest $request, Lodge $lodge, Membership $membership)
    {
        $this->allow($lodge, $membership);
        $before = $membership->toArray();
        $membership->update($request->validated());
        Audit::record('membership.updated', $membership, $lodge, $before, $membership->fresh()->toArray());

        return back();
    }

    public function addPastMasterTerm(Request $request, Lodge $lodge, Membership $membership)
    {
        $this->allow($lodge, $membership);
        $data = $request->validate(['year' => 'required|integer|min:1700|max:' . now()->year]);
        $term = PastMasterTerm::firstOrCreate([
            'lodge_id' => $lodge->id,
            'person_id' => $membership->person_id,
            'year' => $data['year'],
        ]);
        Audit::record('past_master_term.created', $term, $lodge, null, $term->toArray());

        return back();
    }

    public function removePastMasterTerm(Request $request, Lodge $lodge, Membership $membership, PastMasterTerm $term)
    {
        $this->allow($lodge, $membership);
        abort_unless($term->lodge_id === $lodge->id && $term->person_id === $membership->person_id, 404);
        $before = $term->toArray();
        $term->delete();
        Audit::record('past_master_term.deleted', $term, $lodge, $before);

        return back();
    }
}
