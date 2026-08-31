<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreLodgeGroupRequest;
use App\Http\Requests\Platform\SynchronizeLodgeGroupMembershipsRequest;
use App\Http\Requests\Platform\UpdateLodgeGroupRequest;
use App\Models\Lodge;
use App\Models\LodgeGroup;
use App\Models\LodgeGroupType;
use App\Services\LodgeGroupService;
use Inertia\Inertia;

class LodgeGroupController extends Controller
{
    public function index()
    {
        return Inertia::render('platform/LodgeGroups', $this->props());
    }

    /** @return array<string, mixed> */
    public function props(): array
    {
        return [
            'groups' => LodgeGroup::query()->with(['type:id,key,name,is_active', 'lodges:id,name,number,status'])->withCount('lodges')
                ->orderBy('archived_at')->orderBy('name')->get()->map(fn(LodgeGroup $group) => [
                    'id' => $group->id,
                    'lodge_group_type_id' => $group->lodge_group_type_id,
                    'type' => $group->type,
                    'name' => $group->name,
                    'slug' => $group->slug,
                    'description' => $group->description,
                    'is_active' => $group->is_active,
                    'has_public_landing_page' => $group->has_public_landing_page,
                    'archived_at' => $group->archived_at,
                    'lodge_count' => $group->lodges_count,
                    'lodge_ids' => $group->lodges->pluck('id')->values(),
                ])->values(),
            'types' => LodgeGroupType::query()->orderBy('sort_order')->orderBy('name')->get(),
            'lodges' => Lodge::query()->orderBy('name')->orderBy('number')->get(['id', 'name', 'number', 'status']),
        ];
    }

    public function store(StoreLodgeGroupRequest $request, LodgeGroupService $service)
    {
        $service->create($request->validated(), $request->user());

        return back()->with('notice', 'Lodge group created.');
    }

    public function update(UpdateLodgeGroupRequest $request, LodgeGroup $lodgeGroup, LodgeGroupService $service)
    {
        $service->update($lodgeGroup, $request->validated(), $request->user());

        return back()->with('notice', 'Lodge group saved.');
    }

    public function archive(LodgeGroup $lodgeGroup, LodgeGroupService $service)
    {
        $service->archive($lodgeGroup, request()->user());

        return back()->with('notice', 'Lodge group archived.');
    }

    public function restore(LodgeGroup $lodgeGroup, LodgeGroupService $service)
    {
        $service->restore($lodgeGroup, request()->user());

        return back()->with('notice', 'Lodge group restored.');
    }

    public function synchronizeLodges(SynchronizeLodgeGroupMembershipsRequest $request, LodgeGroup $lodgeGroup, LodgeGroupService $service)
    {
        $service->synchronizeLodges($lodgeGroup, $request->validated('lodge_ids'), $request->user());

        return back()->with('notice', 'Lodge memberships saved.');
    }
}
