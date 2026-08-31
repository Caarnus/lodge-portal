<?php

namespace App\Http\Controllers;

use App\Models\Lodge;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit;
use App\Services\PersonAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LodgeRoleController extends Controller
{
    public function index(Request $request, Lodge $lodge)
    {
        $this->allowLodge($lodge, 'roles.manage');

        return Inertia::render('roles/Index', [
            'lodge' => $lodge,
            'roles' => $lodge->roles()->with('permissions')->orderByDesc('is_system')->orderBy('name')->get(),
            'permissions' => $this->availablePermissions($request, $lodge),
        ]);
    }

    private function availablePermissions(Request $request, Lodge $lodge)
    {
        if ($request->user()->is_platform_admin) {
            return Permission::query()->orderBy('name')->get();
        }

        return Permission::query()->whereHas('roles', fn($query) => $query->whereHas('users', fn($users) => $users
            ->where('users.id', $request->user()->id)->where('lodge_user_roles.lodge_id', $lodge->id)))->orderBy('name')->get();
    }

    public function assignments(Request $request, Lodge $lodge, PersonAccess $access)
    {
        $this->allowLodge($lodge, 'roles.manage');
        $search = trim((string)$request->query('search'));
        $usersQuery = User::query()->whereIn('person_id', $access->visibleQuery($lodge)->select('people.id'));
        if ($search !== '') {
            $usersQuery->where(fn(Builder $filter) => $filter
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                ->orWhereRaw('LOWER(email) LIKE ?', ['%' . strtolower($search) . '%']));
        }
        $users = $usersQuery->orderBy('name')->paginate(50, ['id', 'name', 'email'])->withQueryString();

        return Inertia::render('roles/Assignments', [
            'lodge' => $lodge,
            'roles' => $lodge->roles()->orderByDesc('is_system')->orderBy('name')->get(),
            'users' => $users,
            'assignments' => DB::table('lodge_user_roles')->where('lodge_id', $lodge->id)
                ->whereIn('user_id', $users->getCollection()->modelKeys())->get(),
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request, Lodge $lodge)
    {
        $this->allowLodge($lodge, 'roles.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles')->where('lodge_id', $lodge->id)],
            'permission_ids' => 'array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);
        $this->assertPermissionsAvailable($request, $lodge, $data['permission_ids'] ?? []);
        $role = $lodge->roles()->create(['name' => $data['name'], 'is_system' => false]);
        $role->permissions()->sync($data['permission_ids'] ?? []);
        Audit::record('role.created', $role, $lodge, null, $role->load('permissions')->toArray());

        return back();
    }

    private function assertPermissionsAvailable(Request $request, Lodge $lodge, array $permissionIds): void
    {
        $available = $this->availablePermissions($request, $lodge)->pluck('id');
        if (collect($permissionIds)->diff($available)->isNotEmpty()) {
            throw ValidationException::withMessages(['permission_ids' => 'A role cannot grant permissions you do not hold.']);
        }
    }

    public function update(Request $request, Lodge $lodge, Role $role)
    {
        $this->allowRole($lodge, $role);
        abort_if($role->is_system, 403, 'Built-in roles cannot be edited.');
        $data = $request->validate(['name' => 'required|string|max:100', 'permission_ids' => 'array', 'permission_ids.*' => 'integer|exists:permissions,id']);
        $this->assertPermissionsAvailable($request, $lodge, $data['permission_ids'] ?? []);
        $before = $role->load('permissions')->toArray();
        $role->update(['name' => $data['name']]);
        $role->permissions()->sync($data['permission_ids'] ?? []);
        Audit::record('role.updated', $role, $lodge, $before, $role->load('permissions')->toArray());

        return back();
    }

    private function allowRole(Lodge $lodge, Role $role): void
    {
        abort_unless($role->lodge_id === $lodge->id, 404);
        $this->allowLodge($lodge, 'roles.manage');
    }

    public function assign(Request $request, Lodge $lodge, PersonAccess $access)
    {
        $this->allowLodge($lodge, 'roles.manage');
        $data = $request->validate(['user_id' => 'required|integer|exists:users,id', 'role_id' => 'required|integer|exists:roles,id']);
        $role = Role::query()->where('lodge_id', $lodge->id)->findOrFail($data['role_id']);
        $user = User::findOrFail($data['user_id']);
        abort_unless($user->person && $access->visibleQuery($lodge)->whereKey($user->person_id)->exists(), 403);
        if ($role->name === 'Administrator' && !$request->user()->is_platform_admin
            && !$request->user()->lodges()->where('lodges.id', $lodge->id)->wherePivot('role_id', $role->id)->exists()) {
            throw ValidationException::withMessages(['role_id' => 'Only an existing lodge Administrator can grant that role.']);
        }
        DB::table('lodge_user_roles')->insertOrIgnore(['lodge_id' => $lodge->id, 'user_id' => $user->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);
        Audit::record('role.assigned', $user, $lodge, null, ['role_id' => $role->id]);

        return back();
    }

    public function unassign(Request $request, Lodge $lodge)
    {
        $this->allowLodge($lodge, 'roles.manage');
        $data = $request->validate(['user_id' => 'required|integer|exists:users,id', 'role_id' => 'required|integer|exists:roles,id']);
        $role = Role::query()->where('lodge_id', $lodge->id)->findOrFail($data['role_id']);
        DB::table('lodge_user_roles')->where(['lodge_id' => $lodge->id, 'user_id' => $data['user_id'], 'role_id' => $role->id])->delete();
        Audit::record('role.unassigned', User::find($data['user_id']), $lodge, ['role_id' => $role->id]);

        return back();
    }
}
