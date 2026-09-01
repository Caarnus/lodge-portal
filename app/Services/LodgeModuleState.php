<?php

namespace App\Services;

use App\Models\Feature;
use App\Models\Lodge;
use App\Models\User;
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
        $state = DB::table('feature_lodge')
            ->where('feature_id', $feature->id)
            ->where('lodge_id', $lodge->id)
            ->first(['is_available', 'is_enabled']);

        $isAvailable = (bool) ($state?->is_available) && $feature->is_active;
        $isEnabled = (bool) ($state?->is_enabled);

        return compact('feature', 'isAvailable', 'isEnabled') + [
            'is_available' => $isAvailable,
            'is_enabled' => $isEnabled,
            'is_effective' => $isAvailable && $isEnabled,
        ];
    }

    public function isEffective(Lodge $lodge, Feature|string $feature): bool
    {
        return $this->resolve($lodge, $feature)['is_effective'];
    }

    public function setAvailability(User $actor, Lodge $lodge, Feature $feature, bool $available): void
    {
        DB::transaction(function () use ($actor, $lodge, $feature, $available) {
            $before = $this->resolve($lodge, $feature);
            DB::table('feature_lodge')->updateOrInsert(
                ['feature_id' => $feature->id, 'lodge_id' => $lodge->id],
                ['is_available' => $available, 'updated_at' => now(), 'created_at' => now()],
            );
            $after = $this->resolve($lodge, $feature);
            Audit::record('lodge_module.availability_updated', $feature, $lodge, $this->auditState($before), $this->auditState($after), $actor);
        });
    }

    public function setPreference(User $actor, Lodge $lodge, Feature $feature, bool $enabled): void
    {
        $before = $this->resolve($lodge, $feature);
        if (! $before['is_available']) {
            throw ValidationException::withMessages(['module' => 'This module is not available to this lodge.']);
        }

        DB::transaction(function () use ($actor, $lodge, $feature, $enabled, $before) {
            DB::table('feature_lodge')->updateOrInsert(
                ['feature_id' => $feature->id, 'lodge_id' => $lodge->id],
                ['is_enabled' => $enabled, 'updated_at' => now(), 'created_at' => now()],
            );
            $after = $this->resolve($lodge, $feature);
            Audit::record('lodge_module.preference_updated', $feature, $lodge, $this->auditState($before), $this->auditState($after), $actor);
        });
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
}
