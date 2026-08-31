<?php

namespace App\Domain\Ritual;

use App\Models\PersonRitualProficiency;
use App\Models\RitualCategory;
use App\Models\RitualPart;
use App\Models\RitualProgramLevel;
use App\Services\Audit;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RitualReferenceService
{
    public function createCategory(array $data): RitualCategory
    {
        return RitualCategory::create(array_merge($data, [
            'key' => $this->key($data['key'] ?? $data['name']),
            'sort_order' => $data['sort_order'] ?? ((int)RitualCategory::max('sort_order') + 10),
            'is_active' => true,
        ]));
    }

    private function key(string $value): string
    {
        return Str::of($value)->slug('_')->limit(100, '')->toString();
    }

    public function updateCategory(RitualCategory $category, array $data): RitualCategory
    {
        $impact = $category->is_active !== (bool)$data['is_active'];
        $affected = $this->impactCount($category);
        $this->requireConfirmation($impact, $data, $affected);
        unset($data['confirm_impact'], $data['key']);
        $before = $category->only(['name', 'description', 'masonic_degree_id', 'sort_order', 'is_active']);
        $category->update($data);
        if ($impact) app(RitualAchievementService::class)->reconcileCategory($category);
        Audit::record('ritual.category_updated', $category, null, $before, ['affected_person_count' => $impact ? $affected : 0]);

        return $category->fresh();
    }

    public function impactCount(RitualCategory|RitualPart|RitualProgramLevel $reference): int
    {
        if ($reference instanceof RitualPart) {
            return PersonRitualProficiency::query()->where('ritual_part_id', $reference->id)->where('performed_for_credit', true)->count();
        }
        if ($reference instanceof RitualCategory) {
            return PersonRitualProficiency::query()->where('performed_for_credit', true)
                ->whereIn('ritual_part_id', RitualPart::query()->select('id')->where('ritual_category_id', $reference->id))->count();
        }

        return PersonRitualProficiency::query()->where('performed_for_credit', true)->distinct('person_id')->count('person_id');
    }

    private function requireConfirmation(bool $impact, array $data, int $affected): void
    {
        if ($impact && empty($data['confirm_impact'])) {
            throw ValidationException::withMessages(['confirm_impact' => "Confirm this change. It affects {$affected} credited member records."]);
        }
    }

    public function createPart(array $data): RitualPart
    {
        unset($data['confirm_impact']);
        $data['point_value'] = $data['counts_toward_program'] ? $data['point_value'] : null;

        return RitualPart::create(array_merge($data, [
            'key' => $this->key($data['key'] ?? $data['name']),
            'sort_order' => $data['sort_order'] ?? ((int)RitualPart::where('ritual_category_id', $data['ritual_category_id'])->max('sort_order') + 10),
            'is_active' => true,
        ]));
    }

    public function updatePart(RitualPart $part, array $data): RitualPart
    {
        $impact = $part->is_active !== (bool)$data['is_active']
            || $part->counts_toward_program !== (bool)$data['counts_toward_program']
            || $part->ritual_category_id !== (int)$data['ritual_category_id']
            || (int)$part->point_value !== (int)($data['point_value'] ?? 0);
        $affected = $this->impactCount($part);
        $this->requireConfirmation($impact, $data, $affected);
        unset($data['confirm_impact'], $data['key']);
        $data['point_value'] = $data['counts_toward_program'] ? ($data['point_value'] ?? null) : null;
        $before = $part->only(['ritual_category_id', 'name', 'description', 'sort_order', 'counts_toward_program', 'point_value', 'is_active']);
        $part->update($data);
        if ($impact) app(RitualAchievementService::class)->reconcilePart($part);
        Audit::record('ritual.part_updated', $part, null, $before, ['affected_person_count' => $impact ? $affected : 0]);

        return $part->fresh();
    }

    public function createLevel(array $data): RitualProgramLevel
    {
        return RitualProgramLevel::create(array_merge($data, [
            'key' => $this->key($data['key'] ?? $data['name']),
            'sort_order' => $data['sort_order'] ?? ((int)RitualProgramLevel::max('sort_order') + 10),
            'is_active' => true,
        ]));
    }

    public function updateLevel(RitualProgramLevel $level, array $data): RitualProgramLevel
    {
        $impact = (int)$level->point_threshold !== (int)$data['point_threshold'] || $level->is_active !== (bool)$data['is_active'];
        $affected = $this->impactCount($level);
        $this->requireConfirmation($impact, $data, $affected);
        unset($data['confirm_impact'], $data['key']);
        $before = $level->only(['name', 'point_threshold', 'sort_order', 'is_active']);
        $level->update($data);
        if ($impact) app(RitualAchievementService::class)->reconcileLevels();
        Audit::record('ritual.level_updated', $level, null, $before, ['affected_person_count' => $impact ? $affected : 0]);

        return $level->fresh();
    }
}
