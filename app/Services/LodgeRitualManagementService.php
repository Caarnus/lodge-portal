<?php

namespace App\Services;

use App\Domain\Ritual\RitualProgress;
use App\Models\Lodge;
use App\Models\Membership;
use App\Models\Person;
use App\Models\PersonRitualAvailability;
use App\Models\RitualPart;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LodgeRitualManagementService
{
    public function __construct(private readonly RitualProgress $progress) {}

    public function update(Lodge $lodge, Membership $membership, array $data): void
    {
        DB::transaction(function () use ($lodge, $membership, $data) {
            $membership = Membership::query()
                ->whereKey($membership->id)
                ->where('lodge_id', $lodge->id)
                ->whereNull('end_date')
                ->whereHas('status', fn ($query) => $query->where('key', 'active'))
                ->whereHas('person', fn ($query) => $query->where('is_deceased', false)->whereNull('merged_at'))
                ->lockForUpdate()
                ->firstOrFail();
            $person = Person::query()->lockForUpdate()->findOrFail($membership->person_id);
            $parts = RitualPart::query()
                ->with('category')
                ->whereIn('id', collect($data['parts'])->pluck('ritual_part_id'))
                ->where('is_active', true)
                ->whereHas('category', fn ($query) => $query->where('is_active', true))
                ->get()
                ->keyBy('id');

            if ($parts->count() !== count($data['parts'])) {
                throw ValidationException::withMessages(['parts' => 'Only active ritual parts may be updated.']);
            }
            if (collect($data['windows'])->map(fn ($window) => $window['day_of_week'].'|'.$window['daypart'])->unique()->count() !== count($data['windows'])) {
                throw ValidationException::withMessages(['windows' => 'Availability windows must be unique.']);
            }

            foreach ($data['parts'] as $partData) {
                $this->progress->updateProficiency($person, $parts->get($partData['ritual_part_id']), $partData);
            }

            PersonRitualAvailability::query()->where('person_id', $person->id)->delete();
            foreach ($data['windows'] as $window) {
                PersonRitualAvailability::query()->create([
                    'person_id' => $person->id,
                    'day_of_week' => $window['day_of_week'],
                    'daypart' => $window['daypart'],
                    'is_enabled' => true,
                ]);
            }
            Audit::record('ritual.availability.updated', $person, $lodge, null, ['window_count' => count($data['windows'])]);
        });
    }
}
