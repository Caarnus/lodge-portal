<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Services\PersonMergeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PersonMergeController extends Controller
{
    public function create(Request $request)
    {
        abort_unless($request->user()->is_platform_admin, 403);
        $people = Person::query()->withCount('memberships')->orderBy('legal_last_name')->orderBy('legal_first_name')
            ->get(['id', 'name', 'legal_first_name', 'legal_middle_name', 'legal_last_name', 'legal_suffix', 'preferred_name',
                'email', 'phone', 'mailing_city', 'mailing_state', 'birth_date', 'is_deceased']);

        return Inertia::render('platform/PersonMerge', ['people' => $people]);
    }

    public function store(Request $request, PersonMergeService $merger)
    {
        abort_unless($request->user()->is_platform_admin, 403);
        $data = $request->validate(['source_person_id' => 'required|integer|exists:people,id|different:survivor_person_id', 'survivor_person_id' => 'required|integer|exists:people,id']);
        $survivor = $merger->merge(Person::findOrFail($data['source_person_id']), Person::findOrFail($data['survivor_person_id']));

        return back()->with('notice', "People merged. {$survivor->display_name} is the surviving record.");
    }
}
