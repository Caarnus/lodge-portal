<?php

namespace App\Services;

use App\Models\Feature;
use App\Models\Lodge;
use App\Models\User;
use App\Events\LodgeModuleStateChanged;
use App\Exceptions\LodgeModuleIneffective;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LodgeModuleState
{
    /** @return array{feature: Feature, is_available: bool, is_enabled: bool, is_effective: bool} */
    public function resolve(Lodge $lodge, Feature|string $feature): array
    {
        $feature = $feature instanceof Feature
            ? $feature->fresh()
            : Feature::query()->where('key', $feature)->first();
        abort_unless($feature instanceof Feature, 404);
        return $this->resolveFeature($lodge, $feature);
    }

    public function isEffective(Lodge $lodge, Feature|string $feature): bool
    {
        return $this->resolve($lodge, $feature)['is_effective'];
    }

    /** Fail closed for HTTP and non-HTTP callers alike. */
    public function requireEffective(Lodge $lodge, Feature|string $feature): void
    {
        if (! $this->isEffective($lodge, $feature)) {
            throw new LodgeModuleIneffective("Module [{$this->moduleKey($feature)}] is ineffective for lodge [{$lodge->id}].");
        }
    }

    public function setAvailability(User $actor, Lodge $lodge, Feature $feature, bool $available): void
    {
        DB::transaction(function () use ($actor, $lodge, $feature, $available) {
            [$feature, $before, $state] = $this->lockedState($lodge, $feature);
            if ($before['is_available'] === $available) {
                return;
            }
            $this->writeState($lodge, $feature, $state, 'is_available', $available);
            $after = $this->resolveFeature($lodge, $feature);
            Audit::record('lodge_module.availability_updated', $feature, $lodge, $this->auditState($before), $this->auditState($after), $actor);
            DB::afterCommit(fn () => event(new LodgeModuleStateChanged($lodge->id, $feature->key, $this->transitionState($before), $this->transitionState($after), $actor->id, 'availability')));
        });
    }

    public function setPreference(User $actor, Lodge $lodge, Feature $feature, bool $enabled): void
    {
        DB::transaction(function () use ($actor, $lodge, $feature, $enabled) {
            [$feature, $before, $state] = $this->lockedState($lodge, $feature);
            if (! $before['is_available']) {
                throw ValidationException::withMessages(['module' => 'This module is not available to this lodge.']);
            }
            if ($before['is_enabled'] === $enabled) {
                return;
            }
            $this->writeState($lodge, $feature, $state, 'is_enabled', $enabled);
            $after = $this->resolveFeature($lodge, $feature);
            Audit::record('lodge_module.preference_updated', $feature, $lodge, $this->auditState($before), $this->auditState($after), $actor);
            DB::afterCommit(fn () => event(new LodgeModuleStateChanged($lodge->id, $feature->key, $this->transitionState($before), $this->transitionState($after), $actor->id, 'preference')));
        });
    }

    /** @return array{0: Feature, 1: array{feature: Feature, is_available: bool, is_enabled: bool, is_effective: bool}, 2: object|null} */
    private function lockedState(Lodge $lodge, Feature $feature): array
    {
        // Locking the definition serializes creation of the otherwise-absent pair row.
        $feature = Feature::query()->lockForUpdate()->findOrFail($feature->id);
        $state = DB::table('feature_lodge')->where('feature_id', $feature->id)
            ->where('lodge_id', $lodge->id)->lockForUpdate()->first(['is_available', 'is_enabled', 'created_at']);

        return [$feature, $this->resolveFeature($lodge, $feature, $state), $state];
    }

    private function writeState(Lodge $lodge, Feature $feature, ?object $state, string $column, bool $value): void
    {
        if ($state) {
            DB::table('feature_lodge')->where('feature_id', $feature->id)->where('lodge_id', $lodge->id)
                ->update([$column => $value, 'updated_at' => now()]);

            return;
        }

        DB::table('feature_lodge')->insert([
            'feature_id' => $feature->id,
            'lodge_id' => $lodge->id,
            'is_available' => $column === 'is_available' ? $value : false,
            'is_enabled' => $column === 'is_enabled' ? $value : false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{feature: Feature, is_available: bool, is_enabled: bool, is_effective: bool} */
    private function resolveFeature(Lodge $lodge, Feature $feature, ?object $state = null): array
    {
        $state ??= DB::table('feature_lodge')->where('feature_id', $feature->id)->where('lodge_id', $lodge->id)
            ->first(['is_available', 'is_enabled']);
        $isAvailable = (bool) ($state?->is_available);
        $isEnabled = (bool) ($state?->is_enabled);

        return ['feature' => $feature, 'is_available' => $isAvailable, 'is_enabled' => $isEnabled,
            'is_effective' => $feature->is_active && $isAvailable && $isEnabled];
    }

    /** @param array{feature: Feature, is_available: bool, is_enabled: bool, is_effective: bool} $state */
    private function auditState(array $state): array
    {
        return [
            'module' => $state['feature']->key,
            'is_available' => $state['is_available'],
            'is_enabled' => $state['is_enabled'],
            'is_effective' => $state['is_effective'],
        ];
    }

    /** @param array{feature: Feature, is_available: bool, is_enabled: bool, is_effective: bool} $state */
    private function transitionState(array $state): array
    {
        return [
            'is_available' => $state['is_available'],
            'is_enabled' => $state['is_enabled'],
            'is_effective' => $state['is_effective'],
        ];
    }

    private function moduleKey(Feature|string $feature): string
    {
        return $feature instanceof Feature ? $feature->key : $feature;
    }
}
