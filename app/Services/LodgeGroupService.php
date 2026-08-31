<?php

namespace App\Services;

use App\Models\Lodge;
use App\Models\LodgeGroup;
use App\Models\LodgeGroupType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LodgeGroupService
{
    public function create(array $data, ?User $actor = null): LodgeGroup
    {
        $validated = $this->validator($data)->validate();
        $this->requireActiveType((int)$validated['lodge_group_type_id']);

        return DB::transaction(function () use ($validated, $actor): LodgeGroup {
            $group = LodgeGroup::create($validated + [
                    'created_by' => $this->actorId($actor),
                    'updated_by' => $this->actorId($actor),
                ]);
            Audit::record('lodge_group.created', $group, null, null, $group->only(['id', 'name', 'slug', 'lodge_group_type_id', 'is_active', 'has_public_landing_page']));

            return $group;
        });
    }

    private function validator(array $data, ?LodgeGroup $group = null)
    {
        return Validator::make($data, [
            'lodge_group_type_id' => ['required', 'integer', Rule::exists('lodge_group_types', 'id')],
            'name' => ['required', 'string', 'max:255', Rule::unique('lodge_groups', 'name')->ignore($group)],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('lodge_groups', 'slug')->ignore($group)],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
            'has_public_landing_page' => ['required', 'boolean'],
        ]);
    }

    private function requireActiveType(int $typeId): void
    {
        if (!LodgeGroupType::query()->whereKey($typeId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['lodge_group_type_id' => 'Select an active group type.']);
        }
    }

    private function actorId(?User $actor): ?int
    {
        return $actor?->id ?? auth()->id();
    }

    public function archive(LodgeGroup $group, ?User $actor = null): LodgeGroup
    {
        $this->ensureNotArchived($group);

        return DB::transaction(function () use ($group, $actor): LodgeGroup {
            $group->update(['archived_at' => now(), 'updated_by' => $this->actorId($actor)]);
            Audit::record('lodge_group.archived', $group, null, ['archived_at' => null], ['archived_at' => $group->archived_at?->toAtomString()]);

            return $group->fresh();
        });
    }

    private function ensureNotArchived(LodgeGroup $group): void
    {
        if ($group->archived_at !== null) {
            throw ValidationException::withMessages(['group' => 'Archived groups are read-only.']);
        }
    }

    public function update(LodgeGroup $group, array $data, ?User $actor = null): LodgeGroup
    {
        $this->ensureNotArchived($group);
        $validated = $this->validator($data, $group)->validate();
        if ((int)$validated['lodge_group_type_id'] !== $group->lodge_group_type_id) {
            $this->requireActiveType((int)$validated['lodge_group_type_id']);
        }
        $before = $group->only(['lodge_group_type_id', 'name', 'slug', 'description', 'is_active', 'has_public_landing_page']);

        return DB::transaction(function () use ($group, $validated, $actor, $before): LodgeGroup {
            $group->update($validated + ['updated_by' => $this->actorId($actor)]);
            $fresh = $group->fresh();
            $action = $before['is_active'] !== $fresh->is_active
                ? ($fresh->is_active ? 'lodge_group.activated' : 'lodge_group.deactivated')
                : 'lodge_group.updated';
            Audit::record($action, $group, null, $before, $fresh->only(['lodge_group_type_id', 'name', 'slug', 'description', 'is_active', 'has_public_landing_page']));

            return $fresh;
        });
    }

    public function restore(LodgeGroup $group, ?User $actor = null): LodgeGroup
    {
        if ($group->archived_at === null) {
            throw ValidationException::withMessages(['group' => 'This group is not archived.']);
        }

        return DB::transaction(function () use ($group, $actor): LodgeGroup {
            $before = $group->archived_at?->toAtomString();
            $group->update(['archived_at' => null, 'updated_by' => $this->actorId($actor)]);
            Audit::record('lodge_group.restored', $group, null, ['archived_at' => $before], ['archived_at' => null]);

            return $group->fresh();
        });
    }

    public function synchronizeLodges(LodgeGroup $group, array $lodgeIds, ?User $actor = null): LodgeGroup
    {
        $this->ensureNotArchived($group);
        $lodgeIds = array_values(array_unique(array_map('intval', $lodgeIds)));
        if (count($lodgeIds) !== Lodge::query()->whereIn('id', $lodgeIds)->count()) {
            throw ValidationException::withMessages(['lodge_ids' => 'One or more lodges do not exist.']);
        }

        return DB::transaction(function () use ($group, $lodgeIds, $actor): LodgeGroup {
            $before = $group->lodges()->pluck('lodges.id')->map(fn($id) => (int)$id)->all();
            $toAttach = array_values(array_diff($lodgeIds, $before));
            $toDetach = array_values(array_diff($before, $lodgeIds));
            if ($toDetach) {
                $group->lodges()->detach($toDetach);
            }
            if ($toAttach) {
                $group->lodges()->attach(array_fill_keys($toAttach, ['created_by' => $this->actorId($actor)]));
            }
            Audit::record('lodge_group.memberships_synchronized', $group, null, ['lodge_ids' => array_slice($before, 0, 100)], ['lodge_ids' => array_slice($lodgeIds, 0, 100)]);

            return $group->fresh('lodges');
        });
    }
}
