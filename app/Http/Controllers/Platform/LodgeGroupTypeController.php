<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreLodgeGroupTypeRequest;
use App\Http\Requests\Platform\UpdateLodgeGroupTypeRequest;
use App\Models\LodgeGroupType;
use App\Services\LodgeGroupTypeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LodgeGroupTypeController extends Controller
{
    public function index(LodgeGroupController $groups)
    {
        return Inertia::render('platform/LodgeGroups', $groups->props());
    }

    public function store(StoreLodgeGroupTypeRequest $request, LodgeGroupTypeService $service)
    {
        $service->create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return back()->with('notice', 'Lodge group type created.');
    }

    public function update(UpdateLodgeGroupTypeRequest $request, LodgeGroupType $lodgeGroupType, LodgeGroupTypeService $service)
    {
        $service->update($lodgeGroupType, $request->validated());

        return back()->with('notice', 'Lodge group type saved.');
    }

    public function status(Request $request, LodgeGroupType $lodgeGroupType, LodgeGroupTypeService $service)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $service->update($lodgeGroupType, $lodgeGroupType->only(['name', 'description', 'sort_order']) + $data);

        return back()->with('notice', 'Lodge group type status saved.');
    }

    public function destroy(LodgeGroupType $lodgeGroupType, LodgeGroupTypeService $service)
    {
        $service->delete($lodgeGroupType);

        return back()->with('notice', 'Lodge group type deleted.');
    }
}
