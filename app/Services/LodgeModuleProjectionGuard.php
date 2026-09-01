<?php

namespace App\Services;

use App\Models\Lodge;
use App\Models\User;

class LodgeModuleProjectionGuard
{
    public function __construct(private readonly LodgeModuleState $states)
    {
    }

    public function canProjectPublic(Lodge $lodge, string $module): bool
    {
        return $this->states->isEffective($lodge, $module);
    }

    public function canProjectSearch(Lodge $lodge, string $module): bool
    {
        return $this->states->isEffective($lodge, $module);
    }

    public function canAccessWorkspace(User $user, Lodge $lodge, string $module, string $permission): bool
    {
        return $this->states->isEffective($lodge, $module)
            && $user->hasLodgePermission($lodge, $permission);
    }

    public function publicOr(Lodge $lodge, string $module, callable $projection, mixed $unavailable = null): mixed
    {
        return $this->canProjectPublic($lodge, $module) ? $projection() : $unavailable;
    }
}
