<?php

namespace App\Listeners;

use App\Events\LodgeModuleStateChanged;
use App\Services\LodgeModuleCache;
use App\Services\LodgeModuleSearchProjection;

class InvalidateLodgeModuleProjections
{
    public function __construct(
        private readonly LodgeModuleCache $cache,
        private readonly LodgeModuleSearchProjection $search,
    )
    {
    }

    public function handle(LodgeModuleStateChanged $event): void
    {
        // Versioned keys invalidate cache entries without relying on cache-store-specific tags.
        // Search/public adapters recheck the same state at projection time, so stale indexes fail closed.
        $this->cache->invalidate($event);
        $this->search->invalidate($event);
    }
}
