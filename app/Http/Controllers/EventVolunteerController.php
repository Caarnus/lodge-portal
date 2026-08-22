<?php

namespace App\Http\Controllers;

use App\Domain\Events\VolunteerCommitmentService;
use App\Domain\Events\VolunteerEligibility;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventVolunteerCommitment;
use App\Models\EventVolunteerPosition;
use App\Models\Lodge;
use App\Models\Person;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventVolunteerController extends Controller
{
    public function index(Request $request, Lodge $lodge, Event $event, EventOccurrence $occurrence)
    {
        $this->authorize($request, $lodge, $event, $occurrence);
        $positions = EventVolunteerPosition::query()->where('event_id', $event->id)->where(fn ($q) => $q->whereNull('event_occurrence_id')->orWhere('event_occurrence_id', $occurrence->id))->with(['commitments' => fn ($q) => $q->where('event_occurrence_id', $occurrence->id)->with('person')])->orderBy('sort_order')->orderBy('name')->get();

        return Inertia::render('events/Volunteers', compact('lodge', 'event', 'occurrence', 'positions'));
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
