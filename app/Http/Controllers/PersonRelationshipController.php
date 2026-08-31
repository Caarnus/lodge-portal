<?php

namespace App\Http\Controllers;

use App\Models\Lodge;
use App\Models\Person;
use App\Models\PersonRelationship;
use App\Models\RelationshipType;
use App\Services\Audit;
use App\Services\PersonAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PersonRelationshipController extends Controller
{
    public function store(Request $request, Lodge $lodge, Person $person, PersonAccess $access)
    {
        abort_unless($access->canView($request->user(), $lodge, $person), 404);
        $this->allowLodge($lodge, 'relationships.manage');
        $data = $request->validate([
            'related_person_id' => ['nullable', 'required_without:related_person', 'integer', Rule::exists('people', 'id')->whereNull('deleted_at')],
            'related_person' => 'nullable|array|required_without:related_person_id',
            'related_person.legal_first_name' => 'required_with:related_person|string|max:100',
            'related_person.legal_last_name' => 'required_with:related_person|string|max:100',
            'related_person.preferred_name' => 'nullable|string|max:100',
            'related_person.email' => ['nullable', 'email:rfc', 'max:255', Rule::unique('people', 'email')],
            'related_person.phone' => 'nullable|string|max:50',
            'relationship_type_id' => ['required', 'integer', Rule::exists('relationship_types', 'id')->where('is_active', true)],
            'relationship_subject' => ['nullable', Rule::in(['current', 'related'])],
        ]);
        $relatedPerson = isset($data['related_person_id']) ? Person::findOrFail($data['related_person_id']) : null;
        if ($relatedPerson?->is($person)) {
            throw ValidationException::withMessages(['related_person_id' => 'A person cannot be related to themselves.']);
        }
        $relationship = DB::transaction(function () use ($data, $relatedPerson, $person, $lodge, $access, $request) {
            if (!$relatedPerson) {
                $related = $data['related_person'];
                $related['email'] = filled($related['email'] ?? null) ? strtolower(trim($related['email'])) : null;
                $related['name'] = trim($related['legal_first_name'] . ' ' . $related['legal_last_name']);
                $relatedPerson = Person::create($related);
            }
            $selectedType = RelationshipType::findOrFail($data['relationship_type_id']);
            $relationshipTypeId = ($data['relationship_subject'] ?? 'current') === 'related'
                ? RelationshipType::query()->where('key', $selectedType->inverse_key)->value('id')
                : $selectedType->id;
            $relationship = new PersonRelationship(['owning_lodge_id' => $lodge->id, 'person_one_id' => $person->id,
                'person_two_id' => $relatedPerson->id, 'relationship_type_id' => $relationshipTypeId]);
            abort_unless($access->canManageRelationship($request->user(), $lodge, $relationship), 403);
            $this->rejectDuplicate($relationship);
            $relationship->save();

            return $relationship;
        });
        Audit::record('relationship.created', $relationship, $lodge, null, $relationship->toArray());

        return back();
    }

    private function rejectDuplicate(PersonRelationship $candidate): void
    {
        $type = RelationshipType::findOrFail($candidate->relationship_type_id);
        $inverseId = RelationshipType::query()->where('key', $type->inverse_key)->value('id');
        $duplicate = PersonRelationship::query()->when($candidate->exists, fn(Builder $query) => $query->whereKeyNot($candidate->id))
            ->where(function (Builder $query) use ($candidate, $type, $inverseId) {
                $query->where(fn(Builder $direct) => $direct
                    ->where('person_one_id', $candidate->person_one_id)
                    ->where('person_two_id', $candidate->person_two_id)
                    ->where('relationship_type_id', $candidate->relationship_type_id));
                if ($inverseId) {
                    $query->orWhere(fn(Builder $reverse) => $reverse
                        ->where('person_one_id', $candidate->person_two_id)
                        ->where('person_two_id', $candidate->person_one_id)
                        ->where('relationship_type_id', $inverseId));
                }
                if ($type->is_symmetric) {
                    $query->orWhere(fn(Builder $reverse) => $reverse
                        ->where('person_one_id', $candidate->person_two_id)
                        ->where('person_two_id', $candidate->person_one_id)
                        ->where('relationship_type_id', $candidate->relationship_type_id));
                }
            })->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['related_person_id' => 'That relationship is already recorded.']);
        }
    }

    public function destroy(Request $request, Lodge $lodge, PersonRelationship $relationship, PersonAccess $access)
    {
        abort_unless($access->canManageRelationship($request->user(), $lodge, $relationship), 403);
        $before = $relationship->toArray();
        $relationship->delete();
        Audit::record('relationship.deleted', $relationship, $lodge, $before);

        return back();
    }

    public function update(Request $request, Lodge $lodge, PersonRelationship $relationship, PersonAccess $access)
    {
        abort_unless($access->canManageRelationship($request->user(), $lodge, $relationship), 403);
        $data = $request->validate([
            'relationship_type_id' => ['required', 'integer', Rule::exists('relationship_types', 'id')->where('is_active', true)],
            'subject_person_id' => ['required', 'integer', Rule::in([$relationship->person_one_id, $relationship->person_two_id])],
        ]);
        $before = $relationship->toArray();
        $selectedType = RelationshipType::findOrFail($data['relationship_type_id']);
        $relationship->relationship_type_id = (int)$data['subject_person_id'] === $relationship->person_one_id
            ? $selectedType->id
            : RelationshipType::query()->where('key', $selectedType->inverse_key)->value('id');
        $this->rejectDuplicate($relationship);
        $relationship->save();
        Audit::record('relationship.updated', $relationship, $lodge, $before, $relationship->fresh()->toArray());

        return back();
    }
}
