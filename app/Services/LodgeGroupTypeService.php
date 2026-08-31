<?php

namespace App\Services;

use App\Models\LodgeGroupType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LodgeGroupTypeService
{
    public function create(array $data): LodgeGroupType
    {
        $data['key'] = $data['key'] ?? Str::of($data['name'] ?? '')->snake()->toString();
        $validated = $this->validator($data)->validate();

        return DB::transaction(function () use ($validated): LodgeGroupType {
            $type = LodgeGroupType::create($validated + ['sort_order' => $validated['sort_order'] ?? ((int)LodgeGroupType::max('sort_order') + 10)]);
            Audit::record('lodge_group_type.created', $type, null, null, $type->only(['id', 'key', 'name', 'is_active']));

            return $type;
        });
    }

    private function validator(array $data, ?LodgeGroupType $type = null)
    {
        return Validator::make($data, [
            'key' => [$type ? 'sometimes' : 'required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('lodge_group_types', 'key')->ignore($type)],
            'name' => ['required', 'string', 'max:255', Rule::unique('lodge_group_types', 'name')->ignore($type)],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    public function update(LodgeGroupType $type, array $data): LodgeGroupType
    {
        if (isset($data['key']) && $data['key'] !== $type->key) {
            throw ValidationException::withMessages(['key' => 'Group type keys are immutable.']);
        }
        unset($data['key']);
        $validated = $this->validator($data, $type)->validate();
        $before = $type->only(['name', 'description', 'sort_order', 'is_active']);

        return DB::transaction(function () use ($type, $validated, $before): LodgeGroupType {
            $type->update($validated);
            $fresh = $type->fresh();
            $action = $before['is_active'] !== $fresh->is_active
                ? ($fresh->is_active ? 'lodge_group_type.activated' : 'lodge_group_type.deactivated')
                : 'lodge_group_type.updated';
            Audit::record($action, $type, null, $before, $fresh->only(['name', 'description', 'sort_order', 'is_active']));

            return $fresh;
        });
    }

    public function delete(LodgeGroupType $type): void
    {
        if ($type->groups()->exists()) {
            throw ValidationException::withMessages(['type' => 'Deactivate this group type because groups still reference it.']);
        }

        DB::transaction(function () use ($type): void {
            Audit::record('lodge_group_type.deleted', $type, null, $type->only(['id', 'key', 'name']), null);
            $type->delete();
        });
    }
}
