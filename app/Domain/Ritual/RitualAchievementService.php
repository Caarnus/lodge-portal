<?php

namespace App\Domain\Ritual;

use App\Models\Person;
use App\Models\PersonRitualLevelAchievement;
use App\Models\PersonRitualProficiency;
use App\Models\RitualCategory;
use App\Models\RitualPart;
use App\Models\RitualProgramLevel;
use App\Services\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RitualAchievementService
{
    public function __construct(private readonly RitualProgress $progress) {}

    /** @return Collection<int, PersonRitualLevelAchievement> */
    public function reconcile(Person $person): Collection
    {
        return DB::transaction(function () use ($person) {
            $person = Person::query()->lockForUpdate()->findOrFail($person->id);
            $total = $this->progress->currentTotal($person);
            $levels = RitualProgramLevel::query()
                ->where('is_active', true)
                ->where('point_threshold', '<=', $total)
                ->orderBy('point_threshold')
                ->lockForUpdate()
                ->get();
            $created = collect();

            foreach ($levels as $level) {
                $achievement = PersonRitualLevelAchievement::query()->firstOrCreate(
                    [
                        'person_id' => $person->id,
                        'ritual_program_level_id' => $level->id,
                    ],
                    [
                        'achieved_at' => now(),
                        'point_total_at_achievement' => $total,
                        'level_name_snapshot' => $level->name,
                        'threshold_snapshot' => $level->point_threshold,
                    ],
                );
                if ($achievement->wasRecentlyCreated) {
                    $created->push($achievement);
                    Audit::record('ritual.level_achieved', $achievement, null, null, [
                        'level_id' => $level->id,
                        'threshold_snapshot' => $level->point_threshold,
                    ]);
                }
            }

            return $created;
        });
    }

    public function reconcilePart(RitualPart $part): int
    {
        return $this->reconcilePeople(Person::query()->whereIn('id', PersonRitualProficiency::query()
            ->select('person_id')
            ->where('ritual_part_id', $part->id)
            ->where('performed_for_credit', true)));
    }

    public function reconcileCategory(RitualCategory $category): int
    {
        return $this->reconcilePeople(Person::query()->whereIn('id', PersonRitualProficiency::query()
            ->select('person_id')
            ->where('performed_for_credit', true)
            ->whereIn('ritual_part_id', RitualPart::query()->select('id')->where('ritual_category_id', $category->id))));
    }

    public function reconcileLevels(): int
    {
        return $this->reconcilePeople(Person::query()->whereIn('id', PersonRitualProficiency::query()
            ->select('person_id')
            ->where('performed_for_credit', true)));
    }

    private function reconcilePeople(Builder $people): int
    {
        $count = 0;
        $people->orderBy('id')->chunkById(100, function (Collection $people) use (&$count): void {
            foreach ($people as $person) {
                $this->reconcile($person);
                $count++;
            }
        });

        return $count;
    }
}
