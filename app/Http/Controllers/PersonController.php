<?php

namespace App\Http\Controllers;

use App\Http\Requests\PersonRequest;
use App\Models\Lodge;
use App\Models\MasonicDegree;
use App\Models\MembershipStatus;
use App\Models\MembershipType;
use App\Models\Person;
use App\Models\PersonRelationship;
use App\Models\RelationshipType;
use App\Services\Audit;
use App\Services\PersonAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PersonController extends Controller
{
    public function index(Request $request, Lodge $lodge, PersonAccess $access)
    {
        $this->allowLodge($lodge, 'people.view');
        $search = trim((string) $request->query('search'));
        $searchPattern = '%'.strtolower($search).'%';
        $searchDigits = preg_replace('/\D/', '', $search);
        $statusId = $request->integer('status') ?: null;
        $degreeId = $request->integer('degree') ?: null;
        $account = in_array($request->query('account'), ['linked', 'unlinked'], true) ? $request->query('account') : 'all';
        $scope = in_array($request->query('scope'), ['members', 'related'], true) ? $request->query('scope') : 'all';
        $sort = in_array($request->query('sort'), ['name', 'membership', 'phone', 'email', 'location'], true) ? $request->query('sort') : 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $peopleQuery = $access->visibleQuery($lodge)
            ->with([
                'memberships' => fn ($query) => $query->where('lodge_id', $lodge->id)->with(['status', 'type', 'degree']),
                'pastMasterTerms' => fn ($query) => $query->where('lodge_id', $lodge->id)->orderBy('year'),
                'user:id,person_id',
            ]);
        if ($search !== '') {
            $peopleQuery->where(function (Builder $searchQuery) use ($searchPattern, $searchDigits, $lodge) {
                foreach (['legal_first_name', 'legal_last_name', 'preferred_name', 'name', 'email', 'phone', 'mailing_city', 'mailing_state'] as $column) {
                    $searchQuery->orWhereRaw("LOWER({$column}) LIKE ?", [$searchPattern]);
                }
                if (strlen($searchDigits) >= 4) {
                    $searchQuery->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', ''), ' ', ''), '+', '') LIKE ?", ['%'.$searchDigits.'%']);
                }
                $searchQuery->orWhereHas('memberships', fn ($memberships) => $memberships
                    ->where('lodge_id', $lodge->id)->whereRaw('LOWER(member_number) LIKE ?', [$searchPattern]));
            });
        }
        if ($statusId) {
            $peopleQuery->whereHas('memberships', fn (Builder $memberships) => $memberships
                ->where('lodge_id', $lodge->id)->where('membership_status_id', $statusId));
        }
        if ($degreeId) {
            $peopleQuery->whereHas('memberships', fn (Builder $memberships) => $memberships
                ->where('lodge_id', $lodge->id)->where('masonic_degree_id', $degreeId));
        }
        if ($account === 'linked') {
            $peopleQuery->whereHas('user');
        }
        if ($account === 'unlinked') {
            $peopleQuery->whereDoesntHave('user');
        }
        if ($scope === 'members') {
            $peopleQuery->whereHas('memberships', fn (Builder $memberships) => $memberships->where('lodge_id', $lodge->id));
        }
        if ($scope === 'related') {
            $peopleQuery->whereDoesntHave('memberships', fn (Builder $memberships) => $memberships->where('lodge_id', $lodge->id));
        }

        if ($sort === 'membership') {
            $statusName = MembershipStatus::query()->select('membership_statuses.name')
                ->join('memberships', 'memberships.membership_status_id', '=', 'membership_statuses.id')
                ->whereColumn('memberships.person_id', 'people.id')->where('memberships.lodge_id', $lodge->id)->limit(1);
            $degreeName = MasonicDegree::query()->select('masonic_degrees.name')
                ->join('memberships', 'memberships.masonic_degree_id', '=', 'masonic_degrees.id')
                ->whereColumn('memberships.person_id', 'people.id')->where('memberships.lodge_id', $lodge->id)->limit(1);
            $peopleQuery->orderBy($statusName, $direction)->orderBy($degreeName, $direction);
        } elseif ($sort === 'location') {
            $peopleQuery->orderByRaw("LOWER(COALESCE(mailing_city, '')) {$direction}")
                ->orderByRaw("LOWER(COALESCE(mailing_state, '')) {$direction}");
        } elseif ($sort === 'name') {
            $peopleQuery->orderByRaw("LOWER(legal_last_name) {$direction}")
                ->orderByRaw("LOWER(legal_first_name) {$direction}");
        } else {
            $peopleQuery->orderByRaw('LOWER(COALESCE('.$sort.", '')) {$direction}");
        }

        $people = $peopleQuery->orderBy('legal_last_name')->orderBy('legal_first_name')->orderBy('id')->limit(200)->get();
        $manageablePersonIds = $access->manageablePersonIds($request->user(), $lodge, $people->pluck('id')->all());
        $people->each(fn (Person $person) => $person->setAttribute(
            'can_manage',
            $manageablePersonIds->contains($person->id),
        ));
        $this->attachRelationshipSummaries($request, $lodge, $people);

        return Inertia::render('people/Index', [
            'lodge' => $lodge,
            'people' => $people,
            'filters' => ['search' => $search, 'status' => $statusId, 'degree' => $degreeId, 'account' => $account, 'scope' => $scope,
                'sort' => $sort, 'direction' => $direction],
            'membershipTypes' => MembershipType::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'membershipStatuses' => MembershipStatus::query()->orderBy('sort_order')->get(['id', 'name', 'is_active']),
            'degrees' => MasonicDegree::query()->orderBy('sort_order')->get(['id', 'name', 'is_active']),
            'canManage' => $request->user()->hasLodgePermission($lodge, 'people.manage'),
            'canManageMemberships' => $request->user()->hasLodgePermission($lodge, 'memberships.manage'),
        ]);
    }

    public function create(Request $request, Lodge $lodge)
    {
        $this->allowLodge($lodge, 'people.manage');

        return Inertia::render('people/Edit', $this->props($lodge) + ['person' => null, 'membership' => null, 'relationships' => [], 'account' => null, 'canManagePerson' => true]);
    }

    public function store(PersonRequest $request, Lodge $lodge)
    {
        $this->allowLodge($lodge, 'people.manage');
        $data = $request->personData();
        $person = DB::transaction(function () use ($data, $lodge) {
            $person = filled($data['email']) ? Person::withTrashed()->where('email', $data['email'])->first() : null;
            if ($person?->trashed()) {
                throw ValidationException::withMessages(['email' => 'This email belongs to a retired or merged person record.']);
            }
            $person ??= Person::create($data);
            if ($person->memberships()->where('lodge_id', $lodge->id)->exists()) {
                throw ValidationException::withMessages(['email' => 'This person already has a membership in this lodge.']);
            }
            $statusId = MembershipStatus::query()->where('is_default', true)->value('id');
            if (! $statusId) {
                throw ValidationException::withMessages(['membership' => 'No default membership status is configured.']);
            }
            $membership = $person->memberships()->create([
                'lodge_id' => $lodge->id,
                'membership_status_id' => $statusId,
                'primary_lodge_number' => $lodge->number,
            ]);
            Audit::record('person.created_or_reused', $person, $lodge, null, $person->toArray());
            Audit::record('membership.created', $membership, $lodge, null, $membership->toArray());

            return $person;
        });

        $nameMatch = Person::query()->whereKeyNot($person->id)->whereRaw('LOWER(name) = ?', [strtolower($data['name'])])->exists();

        return redirect()->route('lodges.people.edit', [$lodge, $person])
            ->with('notice', $nameMatch ? 'A different person has the same full name. Review before adding more data.' : null);
    }

    public function edit(Request $request, Lodge $lodge, Person $person, PersonAccess $access)
    {
        abort_unless($access->canView($request->user(), $lodge, $person), 404);
        $person->load(['pastMasterTerms' => fn ($query) => $query->where('lodge_id', $lodge->id)->orderBy('year')]);
        $membership = $person->memberships()->where('lodge_id', $lodge->id)->with(['status', 'type', 'degree'])->first();
        $relationships = $person->relationshipsFrom()->with(['personTwo', 'type', 'owningLodge'])->get()
            ->concat($person->relationshipsTo()->with(['personOne', 'type', 'owningLodge'])->get()->all())
            ->filter(fn ($relationship) => $access->canViewRelationship($request->user(), $lodge, $relationship))
            ->map(function ($relationship) use ($person, $access, $request, $lodge) {
                $fromPersonOne = $relationship->person_one_id === $person->id;
                $related = $fromPersonOne ? $relationship->personTwo : $relationship->personOne;

                return [
                    'id' => $relationship->id,
                    'related_person' => $related,
                    'relationship_name' => $fromPersonOne ? $relationship->type->name : $relationship->type->inverse_name,
                    'relationship_statement' => $person->display_name.' is '.lcfirst($fromPersonOne ? $relationship->type->name : $relationship->type->inverse_name).' of '.$related->display_name,
                    'relationship_type_id' => $fromPersonOne ? $relationship->relationship_type_id : RelationshipType::query()
                        ->where('key', $relationship->type->inverse_key)->value('id'),
                    'owning_lodge' => $relationship->owningLodge,
                    'can_manage' => $access->canManageRelationship($request->user(), $lodge, $relationship),
                ];
            })->values();

        return Inertia::render('people/Edit', $this->props($lodge) + [
            'person' => $person,
            'membership' => $membership,
            'relationships' => $relationships,
            'account' => $person->user()->first(['id', 'name', 'email']),
            'canManagePerson' => $access->canManagePerson($request->user(), $lodge, $person),
            'canManageRoles' => $request->user()->hasLodgePermission($lodge, 'roles.manage'),
            'availablePeople' => $access->visibleQuery($lodge)->whereKeyNot($person->id)->orderBy('name')->get(['id', 'name', 'legal_first_name', 'legal_last_name', 'preferred_name']),
        ]);
    }

    public function update(PersonRequest $request, Lodge $lodge, Person $person, PersonAccess $access)
    {
        abort_unless($access->canManagePerson($request->user(), $lodge, $person), 403);
        $before = $person->toArray();
        $person->update($request->personData());
        Audit::record('person.updated', $person, $lodge, $before, $person->fresh()->toArray());

        return back();
    }

    private function props(Lodge $lodge): array
    {
        return [
            'lodge' => $lodge,
            'membershipTypes' => MembershipType::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'membershipStatuses' => MembershipStatus::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'degrees' => MasonicDegree::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'relationshipTypes' => RelationshipType::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ];
    }

    private function attachRelationshipSummaries(Request $request, Lodge $lodge, $people): void
    {
        $summaries = $people->mapWithKeys(fn (Person $person) => [$person->id => []])->all();
        if (! $request->user()->hasLodgePermission($lodge, 'relationships.view')) {
            $people->each(fn (Person $person) => $person->setAttribute('relationship_summaries', []));

            return;
        }

        $personIds = $people->modelKeys();
        $relationships = PersonRelationship::query()
            ->where(fn ($query) => $query->whereIn('person_one_id', $personIds)->orWhereIn('person_two_id', $personIds))
            ->where(fn ($query) => $query
                ->whereHas('personOne.memberships', fn ($memberships) => $memberships->where('lodge_id', $lodge->id)
                    ->whereNull('end_date')->whereHas('status', fn ($statuses) => $statuses->where('key', 'active')))
                ->orWhereHas('personTwo.memberships', fn ($memberships) => $memberships->where('lodge_id', $lodge->id)
                    ->whereNull('end_date')->whereHas('status', fn ($statuses) => $statuses->where('key', 'active'))))
            ->with([
                'type',
                'personOne.memberships' => fn ($query) => $query->where('lodge_id', $lodge->id)->whereNull('end_date')
                    ->whereHas('status', fn ($statuses) => $statuses->where('key', 'active')),
                'personTwo.memberships' => fn ($query) => $query->where('lodge_id', $lodge->id)->whereNull('end_date')
                    ->whereHas('status', fn ($statuses) => $statuses->where('key', 'active')),
            ])->get();

        foreach ($relationships as $relationship) {
            foreach ([$relationship->person_one_id, $relationship->person_two_id] as $subjectId) {
                if (! array_key_exists($subjectId, $summaries)) {
                    continue;
                }
                $fromPersonOne = $subjectId === $relationship->person_one_id;
                $subject = $fromPersonOne ? $relationship->personOne : $relationship->personTwo;
                $related = $fromPersonOne ? $relationship->personTwo : $relationship->personOne;
                $relationshipName = $fromPersonOne ? $relationship->type->name : $relationship->type->inverse_name;
                $summaries[$subjectId][] = [
                    'id' => $relationship->id,
                    'relationship_name' => $relationshipName,
                    'statement' => $subject->display_name.' is '.lcfirst($relationshipName).' of '.$related->display_name,
                    'related_person' => ['id' => $related->id, 'display_name' => $related->display_name],
                    'related_is_lodge_member' => $related->memberships->isNotEmpty(),
                ];
            }
        }

        $people->each(fn (Person $person) => $person->setAttribute('relationship_summaries', $summaries[$person->id]));
    }
}
