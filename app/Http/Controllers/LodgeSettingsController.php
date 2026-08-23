<?php

namespace App\Http\Controllers;

use App\Enums\LodgeStatus;
use App\Http\Requests\LodgeRequest;
use App\Models\EventCategory;
use App\Models\Lodge;
use App\Models\Permission;
use App\Services\Audit;
use Inertia\Inertia;

class LodgeSettingsController extends Controller
{
    private function allow(Lodge $l)
    {
        abort_unless(request()->user()->hasLodgePermission($l, 'lodge.manage'), 403);
    }

    public function edit(Lodge $lodge)
    {
        $this->allow($lodge);

        $user = request()->user();
        $canManageEvents = $user->hasLodgePermission($lodge, 'events.manage');
        $canManageRoles = $user->hasLodgePermission($lodge, 'roles.manage');

        return Inertia::render('lodge/Settings', [
            'lodge' => $lodge,
            'canManageEvents' => $canManageEvents,
            'canManageRoles' => $canManageRoles,
            'eventCategories' => $canManageEvents
                ? EventCategory::query()->orderBy('name')->get()->map(fn (EventCategory $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'is_active' => $category->is_active,
                    'enabled' => $lodge->eventCategories()->whereKey($category->id)->exists(),
                ])->values()
                : [],
            'roles' => $canManageRoles
                ? $lodge->roles()->with('permissions')->orderByDesc('is_system')->orderBy('name')->get()
                : [],
            'permissions' => $canManageRoles
                ? $this->availablePermissions($lodge)
                : [],
        ]);
    }

    public function update(LodgeRequest $r, Lodge $lodge)
    {
        $this->allow($lodge);
        if ($lodge->status === LodgeStatus::DisabledLocked && $r->status === 'active' && !$r->user()->is_platform_admin) {
            abort(403);
        }
        $before = $lodge->toArray();
        $data = $r->safe()->except('logo');
        if ($r->hasFile('logo')) {
            $data['logo_path'] = $r->file('logo')->store('lodges', 'public');
        }
        $lodge->update($data);
        Audit::record('lodge.updated', $lodge, $lodge, $before, $lodge->fresh()->toArray());

        return back();
    }

    private function availablePermissions(Lodge $lodge)
    {
        $user = request()->user();

        if ($user->is_platform_admin) {
            return Permission::query()->orderBy('name')->get();
        }

        return Permission::query()
            ->whereHas('roles', fn ($query) => $query->whereHas('users', fn ($users) => $users
                ->where('users.id', $user->id)
                ->where('lodge_user_roles.lodge_id', $lodge->id)))
            ->orderBy('name')
            ->get();
    }
}
