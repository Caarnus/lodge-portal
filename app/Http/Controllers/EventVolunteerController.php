<?php

namespace App\Http\Controllers;

use App\Domain\Events\VolunteerCommitmentService;
use App\Domain\Events\VolunteerEligibility;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventVolunteerCommitment;
use App\Models\EventVolunteerPosition;
use App\Models\Lodge;
use App\Models\Membership;
use App\Models\Person;
use App\Services\PersonAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventVolunteerController extends Controller
{
    public function index(Request $request, Lodge $lodge, Event $event, EventOccurrence $occurrence, PersonAccess $personAccess)
    {
        $this->authorize($request, $lodge, $event, $occurrence);
        $positions = EventVolunteerPosition::query()->where('event_id', $event->id)->where(fn($q) => $q->whereNull('event_occurrence_id')->orWhere('event_occurrence_id', $occurrence->id))->with(['commitments' => fn($q) => $q->where('event_occurrence_id', $occurrence->id)->with(['person', 'reminderDelivery'])])->orderBy('sort_order')->orderBy('name')->get()->map(function (EventVolunteerPosition $position) use ($request, $lodge, $personAccess) {
            return ['id' => $position->id, 'name' => $position->name, 'description' => $position->description, 'needed_count' => $position->needed_count, 'is_active' => $position->is_active, 'commitments' => $position->commitments->map(function (EventVolunteerCommitment $commitment) use ($request, $lodge, $personAccess) {
                $person = $commitment->person;
                $canViewContact = $person && $personAccess->canView($request->user(), $lodge, $person);

                return ['id' => $commitment->id, 'status' => $commitment->status->value, 'committed_at' => $commitment->committed_at, 'person' => ['display_name' => $person?->display_name, 'email' => $canViewContact ? $person->email : null, 'phone' => $canViewContact ? $person->phone : null], 'reminder' => $commitment->reminderDelivery ? ['id' => $commitment->reminderDelivery->id, 'status' => $commitment->reminderDelivery->status->value, 'last_error' => $commitment->reminderDelivery->last_error] : null];
            })->values()];
        })->values();

        $members = Membership::query()->with('person.user')->where('lodge_id', $lodge->id)->whereNull('end_date')->whereHas('status', fn($query) => $query->where('key', 'active'))->get()
            ->map(fn(Membership $membership) => $membership->person)->filter(fn(?Person $person) => $person?->user)->unique('id')->map(fn(Person $person) => ['id' => $person->id, 'display_name' => $person->display_name])->values();

        return Inertia::render('events/Volunteers', compact('lodge', 'event', 'occurrence', 'positions', 'members'));
    }

    public function store(Request $request, Lodge $lodge, Event $event, EventOccurrence $occurrence, VolunteerCommitmentService $service, VolunteerEligibility $eligibility)
    {
        $this->authorize($request, $lodge, $event, $occurrence);
        $data = $request->validate(['position_id' => ['required', 'integer'], 'person_id' => ['required', 'integer']]);
        $position = EventVolunteerPosition::query()->whereKey($data['position_id'])->where('event_id', $event->id)->where('lodge_id', $lodge->id)->firstOrFail();
        $person = Person::query()->whereKey($data['person_id'])->firstOrFail();
        $target = $person->user;
        abort_unless($target && $eligibility->canVolunteer($target, $event), 422);
        $service->commit($occurrence, $position, $target, $request->user());

        return back();
    }

    public function remove(Request $request, Lodge $lodge, Event $event, EventOccurrence $occurrence, EventVolunteerCommitment $commitment, VolunteerCommitmentService $service)
    {
        $this->authorize($request, $lodge, $event, $occurrence);
        abort_unless($commitment->event_occurrence_id === $occurrence->id && $commitment->event_id === $event->id && $commitment->lodge_id === $lodge->id, 404);
        $service->withdraw($commitment, $request->user(), true);

        return back();
    }

    private function authorize(Request $request, Lodge $lodge, Event $event, EventOccurrence $occurrence): void
    {
        abort_unless($event->lodge_id === $lodge->id && $occurrence->event_id === $event->id && $occurrence->lodge_id === $lodge->id, 404);
        abort_unless($request->user()?->hasLodgePermission($lodge, 'events.manage'), 403);
    }
}
