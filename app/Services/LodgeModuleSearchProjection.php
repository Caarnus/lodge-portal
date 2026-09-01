<?php

namespace App\Services;

use App\Events\LodgeModuleStateChanged;
use App\Models\Lodge;
use Illuminate\Support\Facades\Cache;

class LodgeModuleSearchProjection
{
    public function __construct(private readonly LodgeModuleState $states)
    {
    }

    /** Search writers and result projectors both call this predicate. */
    public function canProject(Lodge $lodge, string $module): bool
    {
        return $this->states->isEffective($lodge, $module);
    }

    /**
     * A lightweight, store-neutral invalidation marker for adapters that maintain a
     * module-specific search projection. Result projection still calls canProject().
     */
    public function invalidate(LodgeModuleStateChanged $event): void
    {
        Cache::forever("lodge-module:{$event->lodgeId}:{$event->moduleKey}:search-invalidated-at", now()->toAtomString());
    }
}
