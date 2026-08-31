<?php

namespace App\Domain\Directory;

use App\Enums\DirectoryAudience;
use App\Enums\DirectoryVisibilityScope;
use App\Enums\LodgeStatus;
use App\Models\Lodge;
use App\Models\Membership;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;

class DirectoryAccess
{
    public function canView(User $user, Lodge $lodge, Person $person, DirectoryAudience $audience): bool
    {
        return $this->canBrowse($user, $lodge, $audience)
            && $this->visibleQuery($lodge, $audience)->whereKey($person->id)->exists();
    }

    public function canBrowse(User $user, Lodge $lodge, DirectoryAudience $audience = DirectoryAudience::OwnLodge): bool
    {
        if ($lodge->status !== LodgeStatus::Active || $user->approval_status !== 'approved' || !$user->hasVerifiedEmail()) {
            return false;
        }

        if ($audience === DirectoryAudience::ParticipatingLodges && $user->is_platform_admin) {
            return true;
        }

        $person = $user->person;
        if (!$person || $person->trashed() || $person->merged_at || $person->is_deceased) {
            return false;
        }

        return $user->hasLodgePermission($lodge, 'directory.view')
            && $this->activeMembershipQuery(Membership::query(), $lodge)->where('person_id', $person->id)->exists();
    }

    private function activeMembershipQuery(Builder|Relation $query, ?Lodge $lodge = null): Builder|Relation
    {
        return $query->whereNull('end_date')
            ->whereHas('status', fn(Builder $status) => $status->where('key', 'active'))
            ->whereHas('lodge', fn(Builder $membershipLodge) => $membershipLodge->where('status', LodgeStatus::Active))
            ->when($lodge, fn(Builder $memberships) => $memberships->where('lodge_id', $lodge->id));
    }

    public function visibleQuery(Lodge $lodge, DirectoryAudience $audience): Builder
    {
        return $this->withProjectionRelationships(
            $audience === DirectoryAudience::OwnLodge
                ? $this->ownLodgeQuery($lodge)
                : $this->participatingLodgesQuery(),
            $lodge,
            $audience,
        );
    }

    private function withProjectionRelationships(Builder $query, Lodge $lodge, DirectoryAudience $audience): Builder
    {
        return $query->with([
            'directoryPrivacySetting',
            'memberships' => function (Relation $memberships) use ($lodge, $audience) {
                $this->activeMembershipQuery(
                    $memberships,
                    $audience === DirectoryAudience::OwnLodge ? $lodge : null,
                )->with(['degree', 'lodge:id,name,number,slug']);
            },
        ]);
    }

    public function ownLodgeQuery(Lodge $lodge): Builder
    {
        return $this->baseSubjectQuery()
            ->whereHas('memberships', fn(Builder $memberships) => $this->activeMembershipQuery($memberships, $lodge))
            ->where(function (Builder $privacy) {
                $privacy->whereDoesntHave('directoryPrivacySetting')
                    ->orWhereHas('directoryPrivacySetting', fn(Builder $setting) => $setting
                        ->whereIn('scope', [DirectoryVisibilityScope::OwnLodge, DirectoryVisibilityScope::ParticipatingLodges]));
            });
    }

    private function baseSubjectQuery(): Builder
    {
        return Person::query()
            ->whereNull('merged_at')
            ->where('is_deceased', false)
            ->whereHas('memberships', fn(Builder $memberships) => $this->activeMembershipQuery($memberships));
    }

    public function participatingLodgesQuery(): Builder
    {
        return $this->baseSubjectQuery()
            ->whereHas('directoryPrivacySetting', fn(Builder $setting) => $setting
                ->where('scope', DirectoryVisibilityScope::ParticipatingLodges));
    }

    public function findVisible(Lodge $lodge, Person $person, DirectoryAudience $audience): ?Person
    {
        return $this->visibleQuery($lodge, $audience)->whereKey($person->id)->first();
    }

    public function search(
        Lodge             $lodge,
        DirectoryAudience $audience,
        ?string           $query = null,
        ?int              $degreeId = null,
        ?string           $group = null,
        int               $perPage = 25,
    ): LengthAwarePaginator
    {
        $query = trim((string)$query);
        $perPage = min(max($perPage, 1), 25);
        $people = $this->visibleQuery($lodge, $audience);

        if ($query !== '') {
            $this->applySearch($people, $query);
        }
        if ($degreeId) {
            $this->applyDegreeFilter($people, $lodge, $audience, $degreeId);
        }
        if ($audience === DirectoryAudience::ParticipatingLodges && filled($group)) {
            $this->applyGroupFilter($people, $group);
        }

        return $people->orderByRaw("LOWER(COALESCE(legal_last_name, name, ''))")
            ->orderByRaw("LOWER(COALESCE(preferred_name, legal_first_name, name, ''))")
            ->orderBy('id')
            ->paginate($perPage)
            ->through(fn(Person $person) => $this->project($person, $lodge, $audience));
    }

    private function applySearch(Builder $people, string $query): void
    {
        $pattern = '%' . mb_strtolower($query) . '%';
        $digits = preg_replace('/\D/', '', $query);

        $people->where(function (Builder $matches) use ($pattern, $digits) {
            $matches->where(function (Builder $name) use ($pattern) {
                foreach (['legal_first_name', 'legal_last_name', 'preferred_name', 'name'] as $column) {
                    $name->orWhereRaw("LOWER({$column}) LIKE ?", [$pattern]);
                }
            })->orWhere(function (Builder $email) use ($pattern) {
                $email->whereHas('directoryPrivacySetting', fn(Builder $privacy) => $privacy->where('show_email', true))
                    ->whereRaw('LOWER(email) LIKE ?', [$pattern]);
            });

            if (strlen($digits) >= 4) {
                $matches->orWhere(function (Builder $phone) use ($digits) {
                    $phone->whereHas('directoryPrivacySetting', fn(Builder $privacy) => $privacy->where('show_phone', true))
                        ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', ''), ' ', ''), '+', '') LIKE ?", ['%' . $digits . '%']);
                });
            }
        });
    }

    private function applyDegreeFilter(Builder $people, Lodge $lodge, DirectoryAudience $audience, int $degreeId): void
    {
        $people->whereHas('directoryPrivacySetting', fn(Builder $privacy) => $privacy->where('show_degree', true));

        if ($audience === DirectoryAudience::OwnLodge) {
            $people->whereHas('memberships', fn(Builder $memberships) => $this->activeMembershipQuery($memberships, $lodge)
                ->where('masonic_degree_id', $degreeId));

            return;
        }

        $people->where($this->highestActiveDegreeIdQuery(), '=', $degreeId);
    }

    private function highestActiveDegreeIdQuery(): Builder
    {
        return Membership::query()->select('memberships.masonic_degree_id')
            ->join('membership_statuses', 'membership_statuses.id', '=', 'memberships.membership_status_id')
            ->join('lodges', 'lodges.id', '=', 'memberships.lodge_id')
            ->join('masonic_degrees', 'masonic_degrees.id', '=', 'memberships.masonic_degree_id')
            ->whereColumn('memberships.person_id', 'people.id')
            ->whereNull('memberships.end_date')
            ->where('membership_statuses.key', 'active')
            ->where('lodges.status', LodgeStatus::Active)
            ->orderByDesc('masonic_degrees.sort_order')
            ->orderByDesc('memberships.masonic_degree_id')
            ->limit(1);
    }

    private function applyGroupFilter(Builder $people, string $group): void
    {
        $groupId = \App\Models\LodgeGroup::query()->active()
            ->where(fn(Builder $groups) => $groups->where('slug', $group)->orWhere('id', is_numeric($group) ? (int)$group : 0))
            ->value('id');
        if (!$groupId) {
            throw \Illuminate\Validation\ValidationException::withMessages(['group' => 'Select an active lodge group.']);
        }
        $people->whereHas('memberships', fn(Builder $memberships) => $this->activeMembershipQuery($memberships)
            ->whereHas('lodge.lodgeGroups', fn(Builder $groups) => $groups->whereKey($groupId)));
    }

    public function project(Person $person, Lodge $lodge, DirectoryAudience $audience): array
    {
        $privacy = $person->directoryPrivacySetting;
        $showEmail = (bool)$privacy?->show_email;
        $showPhone = (bool)$privacy?->show_phone;
        $showAddress = (bool)$privacy?->show_address;
        $showDegree = (bool)$privacy?->show_degree;

        return [
            'id' => $person->id,
            'display_name' => $person->display_name,
            'email' => $showEmail ? $person->email : null,
            'phone' => $showPhone ? $person->phone : null,
            'address' => $showAddress ? [
                'line_1' => $person->mailing_address_line_1,
                'line_2' => $person->mailing_address_line_2,
                'city' => $person->mailing_city,
                'state' => $person->mailing_state,
                'postal_code' => $person->mailing_postal_code,
            ] : null,
            'degree' => $showDegree ? $this->degreeName($person, $lodge, $audience) : null,
            'profile_photo_url' => $privacy?->show_profile_photo
            && $person->profile_photo_status === 'ready'
            && $person->profile_photo_derivative_path
                ? route('lodges.directory.photo', ['lodge' => $lodge, 'person' => $person, 'audience' => $audience->value])
                : null,
            'affiliations' => $audience === DirectoryAudience::ParticipatingLodges
                ? $person->memberships->map(fn(Membership $membership) => [
                    'id' => $membership->lodge->id,
                    'name' => $membership->lodge->name,
                    'number' => $membership->lodge->number,
                    'slug' => $membership->lodge->slug,
                ])->values()->all()
                : [],
        ];
    }

    private function degreeName(Person $person, Lodge $lodge, DirectoryAudience $audience): ?string
    {
        $memberships = $person->memberships;
        if ($audience === DirectoryAudience::OwnLodge) {
            return $memberships->firstWhere('lodge_id', $lodge->id)?->degree?->name;
        }

        return $memberships->sortByDesc(fn(Membership $membership) => [
            $membership->degree?->sort_order ?? -1,
            $membership->masonic_degree_id ?? -1,
        ])->first()?->degree?->name;
    }
}
