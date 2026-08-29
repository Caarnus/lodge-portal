<?php

namespace App\Domain\Ritual;

use App\Enums\RitualProficiencyStatus;
use App\Models\Person;
use App\Models\PersonRitualLevelAchievement;
use App\Models\PersonRitualProficiency;
use App\Models\RitualPart;
use App\Services\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RitualProgress
{
    public function currentTotal(Person $person): int
    {
        return (int) PersonRitualProficiency::query()
            ->join('ritual_parts', 'ritual_parts.id', '=', 'person_ritual_proficiencies.ritual_part_id')
            ->join('ritual_categories', 'ritual_categories.id', '=', 'ritual_parts.ritual_category_id')
            ->where('person_ritual_proficiencies.person_id', $person->id)
            ->where('person_ritual_proficiencies.performed_for_credit', true)
            ->where('ritual_parts.is_active', true)
            ->where('ritual_categories.is_active', true)
            ->where('ritual_parts.counts_toward_program', true)
            ->whereNotNull('ritual_parts.point_value')
            ->where('ritual_parts.point_value', '>', 0)
            ->sum('ritual_parts.point_value');
    }

    /** @return Collection<int, PersonRitualProficiency> */
    public function creditedActiveParts(Person $person): Collection
    {
        return $this->creditedParts($person)
            ->filter(fn (PersonRitualProficiency $proficiency) => $this->isActivePointBearingPart($proficiency->part));
    }

    /** @return Collection<int, PersonRitualProficiency> */
    public function creditedRetiredParts(Person $person): Collection
    {
        return $this->creditedParts($person)
            ->filter(fn (PersonRitualProficiency $proficiency) => $proficiency->part?->counts_toward_program)
            ->reject(fn (PersonRitualProficiency $proficiency) => $this->isActivePointBearingPart($proficiency->part));
    }

    /** @return Collection<int, PersonRitualProficiency> */
    public function proficientNonPointParts(Person $person): Collection
    {
        return PersonRitualProficiency::query()
            ->with('part.category')
            ->where('person_id', $person->id)
            ->where('status', RitualProficiencyStatus::Proficient)
            ->whereHas('part', fn ($query) => $query
                ->where('is_active', true)
                ->where('counts_toward_program', false)
                ->whereHas('category', fn ($category) => $category->where('is_active', true)))
            ->get();
    }

    public function projection(Person $person): array
    {
        $currentTotal = $this->currentTotal($person);
        $achievements = PersonRitualLevelAchievement::query()
            ->with('level')
            ->where('person_id', $person->id)
            ->orderBy('threshold_snapshot')
            ->orderBy('achieved_at')
            ->get();
        $highest = $achievements->last();
        $next = \App\Models\RitualProgramLevel::query()
            ->where('is_active', true)
            ->whereNotIn('id', $achievements->pluck('ritual_program_level_id'))
            ->orderBy('point_threshold')
            ->orderBy('sort_order')
            ->first();

        return [
            'current_total' => $currentTotal,
            'credited_active_parts' => $this->creditedActiveParts($person),
            'credited_retired_parts' => $this->creditedRetiredParts($person),
            'proficient_non_point_parts' => $this->proficientNonPointParts($person),
            'achievements' => $achievements,
            'highest_achievement' => $highest,
            'next_level' => $next,
            'remaining_points' => $next ? max(0, $next->point_threshold - $currentTotal) : null,
            'current_total_below_highest_achievement' => $highest !== null && $currentTotal < $highest->threshold_snapshot,
        ];
    }

    public function updateProficiency(Person $person, RitualPart $part, array $data): ?PersonRitualProficiency
    {
        return DB::transaction(function () use ($person, $part, $data) {
            $person = Person::query()->lockForUpdate()->findOrFail($person->id);
            $part = RitualPart::query()->with('category')->lockForUpdate()->findOrFail($part->id);
            $proficiency = PersonRitualProficiency::query()
                ->where('person_id', $person->id)
                ->where('ritual_part_id', $part->id)
                ->lockForUpdate()
                ->first();
            $before = $proficiency?->only(['status', 'interested_in_learning', 'willing_to_assist', 'performed_for_credit', 'first_marked_proficient_on']);
            $attributes = $this->normalizedAttributes($proficiency, $data);

            if ($attributes['status'] !== RitualProficiencyStatus::Proficient) {
                if (($data['willing_to_assist'] ?? false) === true) {
                    throw ValidationException::withMessages(['willing_to_assist' => 'Willingness to assist requires proficient status.']);
                }
                $attributes['willing_to_assist'] = false;
            }
            if (!$proficiency?->performed_for_credit && $attributes['performed_for_credit'] && empty($data['confirm_performed_for_credit'])) {
                throw ValidationException::withMessages(['confirm_performed_for_credit' => 'Confirm that this part was performed from memory in an open lodge.']);
            }
            if (!$proficiency?->performed_for_credit && $attributes['performed_for_credit'] && $attributes['status'] !== RitualProficiencyStatus::Proficient) {
                throw ValidationException::withMessages(['performed_for_credit' => 'Credit requires current proficient status.']);
            }
            if ($attributes['first_marked_proficient_on']?->isFuture()) {
                throw ValidationException::withMessages(['first_marked_proficient_on' => 'The first proficient date cannot be in the future.']);
            }
            if (mb_strlen((string) $attributes['notes']) > 2000) {
                throw ValidationException::withMessages(['notes' => 'Private notes may not exceed 2,000 characters.']);
            }

            if ($this->isDefault($attributes)) {
                $proficiency?->delete();
                $result = null;
            } else {
                $result = $proficiency ?? new PersonRitualProficiency([
                    'person_id' => $person->id,
                    'ritual_part_id' => $part->id,
                ]);
                $result->fill($attributes)->save();
            }

            $performedChanged = ($before['performed_for_credit'] ?? false) !== ($result?->performed_for_credit ?? false);
            if ($performedChanged) {
                app(RitualAchievementService::class)->reconcile($person);
            }
            Audit::record('ritual.proficiency.updated', $result ?? $person, null, $before, [
                'part_id' => $part->id,
                'status' => $attributes['status']->value,
                'interested_in_learning' => $attributes['interested_in_learning'],
                'willing_to_assist' => $attributes['willing_to_assist'],
                'performed_for_credit' => $attributes['performed_for_credit'],
                'first_marked_proficient_on' => $attributes['first_marked_proficient_on']?->toDateString(),
            ]);

            return $result?->fresh('part.category');
        });
    }

    /** @return Collection<int, PersonRitualProficiency> */
    private function creditedParts(Person $person): Collection
    {
        return PersonRitualProficiency::query()
            ->with('part.category')
            ->where('person_id', $person->id)
            ->where('performed_for_credit', true)
            ->get();
    }

    private function isActivePointBearingPart(?RitualPart $part): bool
    {
        return $part !== null
            && $part->is_active
            && $part->category?->is_active
            && $part->counts_toward_program
            && $part->point_value !== null
            && $part->point_value > 0;
    }

    private function normalizedAttributes(?PersonRitualProficiency $proficiency, array $data): array
    {
        $status = $data['status'] ?? $proficiency?->status ?? RitualProficiencyStatus::NotKnown;
        $status = $status instanceof RitualProficiencyStatus ? $status : RitualProficiencyStatus::from($status);
        $date = $data['first_marked_proficient_on'] ?? $proficiency?->first_marked_proficient_on;

        return [
            'status' => $status,
            'interested_in_learning' => (bool) ($data['interested_in_learning'] ?? $proficiency?->interested_in_learning ?? false),
            'willing_to_assist' => (bool) ($data['willing_to_assist'] ?? $proficiency?->willing_to_assist ?? false),
            'performed_for_credit' => (bool) ($data['performed_for_credit'] ?? $proficiency?->performed_for_credit ?? false),
            'first_marked_proficient_on' => $date ? \Illuminate\Support\Carbon::parse($date) : null,
            'notes' => isset($data['notes']) ? ($data['notes'] === '' ? null : $data['notes']) : $proficiency?->notes,
        ];
    }

    private function isDefault(array $attributes): bool
    {
        return $attributes['status'] === RitualProficiencyStatus::NotKnown
            && !$attributes['interested_in_learning']
            && !$attributes['willing_to_assist']
            && !$attributes['performed_for_credit']
            && $attributes['first_marked_proficient_on'] === null
            && $attributes['notes'] === null;
    }
}
