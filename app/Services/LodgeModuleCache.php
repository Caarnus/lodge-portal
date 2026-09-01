<?php

namespace App\Services;

use App\Events\LodgeModuleStateChanged;
use App\Models\Lodge;
use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

class LodgeModuleCache
{
    public function __construct(private readonly LodgeModuleState $states)
    {
    }

    public function key(Lodge $lodge, string $module, string $name): string
    {
        return "lodge-module:{$lodge->id}:{$module}:v{$this->version($lodge->id, $module)}:{$name}";
    }

    public function remember(Lodge $lodge, string $module, string $name, DateInterval|DateTimeInterface|int $ttl, callable $callback): mixed
    {
        if (! $this->states->isEffective($lodge, $module)) {
            return null;
        }

        return Cache::remember($this->key($lodge, $module, $name), $ttl, function () use ($lodge, $module, $callback) {
            // A state may have changed between the cache lookup and callback execution.
            return $this->states->isEffective($lodge, $module) ? $callback() : null;
        });
    }

    public function invalidate(LodgeModuleStateChanged $event): void
    {
        $key = $this->versionKey($event->lodgeId, $event->moduleKey);
        Cache::add($key, 1);
        Cache::increment($key);
    }

    private function version(int $lodgeId, string $module): int
    {
        return (int) Cache::get($this->versionKey($lodgeId, $module), 1);
    }

    private function versionKey(int $lodgeId, string $module): string
    {
        return "lodge-module:{$lodgeId}:{$module}:version";
    }
}
