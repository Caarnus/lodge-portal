<?php

namespace App\Domain\Ritual;

use App\Enums\LodgeStatus;
use App\Enums\RitualVisibilityScope;
use App\Models\Lodge;
use App\Models\LodgeGroup;
use App\Models\Membership;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RitualAssistanceAccess
{
    public function canBrowse(User $user, Lodge $lodge): bool
    {
        $person = $user->person;

        return $lodge->status === LodgeStatus::Active
            && $user->approval_status === 'approved'
            && $user->hasVerifiedEmail()
            && $person !== null
            && !$person->trashed()
            && !$person->is_deceased
            && $person->merged_at === null
            && $this->hasExplicitPermission($user, $lodge, 'ritual.search')
            && $this->activeMembershipQuery(Membership::query(), $lodge)->where('person_id', $person->id)->exists();
    }

    private function hasExplicitPermission(User $user, Lodge $lodge, string $permission): bool
    {
        return DB::table('lodge_user_roles')->join('permission_role', 'lodge_user_roles.role_id', '=', 'permission_role.role_id')->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')->where('lodge_user_roles.user_id', $user->id)->where('lodge_user_roles.lodge_id', $lodge->id)->where('permissions.key', $permission)->exists();
    }

    private function activeMembershipQuery(Builder|Relation $query, ?Lodge $lodge = null): Builder|Relation
    {
        return $query->whereNull('end_date')->whereHas('status', fn(Builder $status) => $status->where('key', 'active'))
            ->whereHas('lodge', fn(Builder $membershipLodge) => $membershipLodge->where('status', LodgeStatus::Active))
            ->when($lodge, fn(Builder|Relation $memberships) => $memberships->where('lodge_id', $lodge->id));
    }

    public function search(Lodge $lodge, array $filters): LengthAwarePaginator
    {
        $query = $this->visibleQuery($lodge, $filters['audience'] ?? 'own_lodge');
        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters);

        return $this->withProjectionRelationships($query)
            ->paginate(min($filters['per_page'] ?? 25, 25))
            ->through(fn(Person $person) => $this->project($person, $filters));
    }

    public function visibleQuery(Lodge $lodge, string $audience = 'own_lodge'): Builder
    {
        $query = Person::query()
            ->whereNull('people.deleted_at')
            ->whereNull('people.merged_at')
            ->where('people.is_deceased', false)
            ->whereHas('ritualSetting', fn(Builder $setting) => $setting->where('visibility_scope', '!=', RitualVisibilityScope::Hidden))
            ->whereHas('ritualProficiencies', fn(Builder $proficiency) => $this->matchingProficiencyQuery($proficiency))
            ->whereHas('memberships', fn(Builder $memberships) => $this->activeMembershipQuery($memberships));

        if ($audience === 'own_lodge') {
            return $query->whereHas('memberships', fn(Builder $memberships) => $this->activeMembershipQuery($memberships, $lodge));
        }

        return $query->whereHas('ritualSetting', fn(Builder $setting) => $setting->where('visibility_scope', RitualVisibilityScope::ParticipatingLodges));
    }

    private function matchingProficiencyQuery(Builder $proficiencies): Builder
    {
        return $proficiencies->where('status', 'proficient')->where('willing_to_assist', true)
            ->whereHas('part', fn(Builder $part) => $part->where('is_active', true)->whereHas('category', fn(Builder $category) => $category->where('is_active', true)));
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['part'])) {
            $query->whereHas('ritualProficiencies', fn(Builder $p) => $this->matchingProficiencyQuery($p)->where('ritual_part_id', $filters['part']));
        }
        if (isset($filters['category'])) {
            $query->whereHas('ritualProficiencies', fn(Builder $p) => $this->matchingProficiencyQuery($p)->whereHas('part', fn(Builder $part) => $part->where('ritual_category_id', $filters['category'])));
        }
        if (isset($filters['degree'])) {
            $query->whereHas('ritualProficiencies', fn(Builder $p) => $this->matchingProficiencyQuery($p)->whereHas('part.category', fn(Builder $category) => $category->where('masonic_degree_id', $filters['degree'])));
        }
        if (isset($filters['lodge'])) {
            $query->whereHas('memberships', fn(Builder $m) => $this->activeMembershipQuery($m)->where('lodge_id', $filters['lodge']));
        }
        if (filled($filters['group'] ?? null)) {
            $groupId = LodgeGroup::query()->active()
                ->where(fn(Builder $groups) => $groups->where('slug', $filters['group'])->orWhere('id', is_numeric($filters['group']) ? (int)$filters['group'] : 0))
                ->value('id');
            if (!$groupId) {
                throw ValidationException::withMessages(['group' => 'Select an active lodge group.']);
            }
            $query->whereHas('memberships', fn(Builder $memberships) => $this->activeMembershipQuery($memberships)
                ->whereHas('lodge.lodgeGroups', fn(Builder $groups) => $groups->whereKey($groupId)));
        }
        if (isset($filters['day_of_week'])) {
            $query->whereHas('ritualAvailabilities', fn(Builder $a) => $a->where('is_enabled', true)->where('day_of_week', $filters['day_of_week'])->where('daypart', $filters['daypart']));
        }
        if (($name = trim((string)($filters['query'] ?? ''))) !== '') {
            $pattern = '%' . mb_strtolower($name) . '%';
            $query->where(fn(Builder $names) => $names->whereRaw('LOWER(legal_first_name) LIKE ?', [$pattern])->orWhereRaw('LOWER(legal_last_name) LIKE ?', [$pattern])->orWhereRaw('LOWER(preferred_name) LIKE ?', [$pattern])->orWhereRaw('LOWER(name) LIKE ?', [$pattern]));
        }
    }

    private function applySort(Builder $query, array $filters): void
    {
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        match ($filters['sort'] ?? 'name') {
            'affiliations' => $query->withCount([
                'memberships as active_affiliations_count' => fn(Builder $memberships) => $this->activeMembershipQuery($memberships),
            ])->orderBy('active_affiliations_count', $direction),
            'roles' => $query->withCount([
                'ritualProficiencies as matching_roles_count' => fn(Builder $proficiencies) => $this->matchingProficiencyQuery($proficiencies),
            ])->orderBy('matching_roles_count', $direction),
            default => $query
                ->orderByRaw("LOWER(COALESCE(legal_last_name, name, '')) {$direction}")
                ->orderByRaw("LOWER(COALESCE(preferred_name, legal_first_name, name, '')) {$direction}"),
        };

        if (($filters['sort'] ?? 'name') !== 'name') {
            $query->orderByRaw("LOWER(COALESCE(legal_last_name, name, ''))")
                ->orderByRaw("LOWER(COALESCE(preferred_name, legal_first_name, name, ''))");
        }

        $query->orderBy('id');
    }

    private function withProjectionRelationships(Builder $query): Builder
    {
        return $query->with([
            'ritualSetting', 'directoryPrivacySetting', 'ritualProficiencies.part.category', 'ritualAvailabilities',
            'memberships' => fn(Relation $memberships) => $this->activeMembershipQuery($memberships)->with('lodge'),
        ]);
    }

    public function project(Person $person, array $filters = [], bool $detail = false): array
    {
        $proficiencies = $person->ritualProficiencies->filter(fn($item) => $this->isMatchingProficiency($item));
        if (!$detail && isset($filters['part'])) {
            $proficiencies = $proficiencies->where('ritual_part_id', $filters['part']);
        }
        if (!$detail && isset($filters['category'])) {
            $proficiencies = $proficiencies->filter(fn($item) => $item->part->ritual_category_id === $filters['category']);
        }
        if (!$detail && isset($filters['degree'])) {
            $proficiencies = $proficiencies->filter(fn($item) => $item->part->category->masonic_degree_id === $filters['degree']);
        }

        $availability = $person->ritualAvailabilities->where('is_enabled', true);
        if (!$detail && isset($filters['day_of_week'])) {
            $availability = $availability->where('day_of_week', $filters['day_of_week'])
                ->filter(fn($item) => $item->daypart->value === $filters['daypart']);
        }
        $privacy = $person->directoryPrivacySetting;

        return [
            'id' => $person->id,
            'display_name' => $person->display_name,
            'affiliations' => $person->memberships->map(fn($membership) => ['id' => $membership->lodge->id, 'name' => $membership->lodge->name, 'number' => $membership->lodge->number, 'slug' => $membership->lodge->slug])->values(),
            'parts' => $proficiencies->map(fn($item) => ['id' => $item->part->id, 'name' => $item->part->name, 'category' => $item->part->category->name, 'self_reported' => true, 'updated_at' => $item->updated_at])->values(),
            'availability' => $availability->map(fn($item) => ['day_of_week' => $item->day_of_week, 'daypart' => $item->daypart->value])->values(),
            'public_availability_note' => $person->ritualSetting?->public_availability_note,
            'email' => $privacy?->show_email ? $person->email : null,
            'phone' => $privacy?->show_phone ? $person->phone : null,
            'ritual_profile_updated_at' => $person->ritualSetting?->updated_at,
            'availability_updated_at' => $person->ritualAvailabilities->max('updated_at'),
        ];
    }

    private function isMatchingProficiency($proficiency): bool
    {
        return $proficiency->status->value === 'proficient' && $proficiency->willing_to_assist && $proficiency->part?->is_active && $proficiency->part->category?->is_active;
    }

    public function findVisible(Lodge $lodge, Person $person, string $audience): ?Person
    {
        return $this->withProjectionRelationships($this->visibleQuery($lodge, $audience))
            ->whereKey($person->id)
            ->first();
    }
}
