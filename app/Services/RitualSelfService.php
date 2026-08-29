<?php

namespace App\Services;

use App\Enums\LodgeStatus;
use App\Enums\RitualDaypart;
use App\Enums\RitualVisibilityScope;
use App\Models\Membership;
use App\Models\Person;
use App\Models\PersonRitualAvailability;
use App\Models\PersonRitualSetting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RitualSelfService
{
    public function __construct(private readonly SelfProfileService $profiles) {}

    public function personFor(User $user): Person
    {
        $person = $this->profiles->personFor($user);
        $active = Membership::query()->where('person_id', $person->id)->whereNull('end_date')
            ->whereHas('status', fn ($query) => $query->where('key', 'active'))
            ->whereHas('lodge', fn ($query) => $query->where('status', LodgeStatus::Active))->exists();
        if (! $active) throw new AuthorizationException('An active membership in an active lodge is required.');
        return $person;
    }

    public function updateSettings(User $user, array $data): PersonRitualSetting
    {
        $person = $this->personFor($user);
        return DB::transaction(function () use ($person, $user, $data) {
            $setting = PersonRitualSetting::query()->lockForUpdate()->firstOrCreate(['person_id' => $person->id]);
            $setting->update(['visibility_scope' => RitualVisibilityScope::from($data['visibility_scope']), 'public_availability_note' => filled($data['public_availability_note'] ?? null) ? trim($data['public_availability_note']) : null, 'updated_by' => $user->id]);
            Audit::record('ritual.settings.updated', $person, null, ['visibility_scope' => $setting->getOriginal('visibility_scope')], ['visibility_scope' => $setting->visibility_scope->value, 'note_changed' => $setting->wasChanged('public_availability_note')]);
            return $setting->fresh();
        });
    }

    public function replaceAvailability(User $user, array $windows): void
    {
        $person = $this->personFor($user);
        foreach ($windows as $window) if (! in_array($window['daypart'], array_map(fn ($daypart) => $daypart->value, RitualDaypart::cases()), true) || $window['day_of_week'] < 1 || $window['day_of_week'] > 7) throw ValidationException::withMessages(['windows' => 'Availability windows are invalid.']);
        DB::transaction(function () use ($person, $windows) {
            PersonRitualAvailability::query()->where('person_id', $person->id)->delete();
            foreach ($windows as $window) PersonRitualAvailability::query()->create(['person_id' => $person->id, 'day_of_week' => $window['day_of_week'], 'daypart' => $window['daypart'], 'is_enabled' => true]);
            Audit::record('ritual.availability.updated', $person, null, null, ['window_count' => count($windows)]);
        });
    }
}
