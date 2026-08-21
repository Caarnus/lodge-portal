<?php

namespace App\Services;

use App\Models\Lodge;
use App\Models\Permission;
use App\Models\Role;

class LodgeRoleCatalog
{
    public const PERMISSIONS = [
        'lodge.manage' => 'Manage lodge identity and settings',
        'registration.review' => 'Review registrations',
        'website.manage' => 'Manage lodge website',
        'website.publish' => 'Publish lodge website',
        'people.view' => 'View lodge-reachable people',
        'people.manage' => 'Manage shared person identity and contact details',
        'memberships.manage' => 'Manage lodge memberships',
        'relationships.view' => 'View reachable family relationships',
        'relationships.manage' => 'Manage qualifying family relationships',
        'officers.manage' => 'Manage current lodge officers',
        'roles.manage' => 'Manage lodge roles and access',
    ];

    public function seedPermissions(): void
    {
        foreach (self::PERMISSIONS as $key => $name) {
            Permission::updateOrCreate(['key' => $key], ['name' => $name]);
        }
    }

    public function ensureFor(Lodge $lodge): void
    {
        $this->seedPermissions();
        $all = Permission::query()->whereIn('key', array_keys(self::PERMISSIONS))->pluck('id');
        $officer = Permission::query()->whereIn('key', ['people.view', 'people.manage', 'memberships.manage', 'relationships.view'])->pluck('id');
        $member = Permission::query()->whereIn('key', ['people.view', 'relationships.view'])->pluck('id');

        foreach (['Administrator', 'Officer', 'Member', 'Non-member'] as $name) {
            $role = Role::firstOrCreate(['lodge_id' => $lodge->id, 'name' => $name], ['is_system' => true]);
            $role->update(['is_system' => true]);
            if ($name === 'Administrator') {
                $role->permissions()->sync($all);
            } elseif ($name === 'Officer') {
                $role->permissions()->sync($officer);
            } elseif ($name === 'Member') {
                $role->permissions()->sync($member);
            } else {
                $role->permissions()->sync([]);
            }
        }
    }
}
