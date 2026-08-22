<?php

namespace App\Http\Controllers;

use App\Domain\Events\VolunteerCommitmentService;
use App\Domain\Events\VolunteerEligibility;
use App\Models\EventOccurrence;
use App\Models\EventVolunteerCommitment;
use App\Models\EventVolunteerPosition;
use App\Models\Lodge;
use Illuminate\Http\Request;

class PublicEventVolunteerController extends Controller
{
    public function store(Request $request, Lodge $lodge, EventOccurrence $occurrence, VolunteerCommitmentService $service, VolunteerEligibility $eligibility)
    {
        abort_unless($occurrence->lodge_id === $lodge->id && $occurrence->event->lodge_id === $lodge->id && $eligibility->canVolunteer($request->user(), $occurrence->event), 404);
        $position = EventVolunteerPosition::query()->whereKey($request->validate(['position_id' => ['required', 'integer']])['position_id'])->where('event_id', $occurrence->event_id)->where('lodge_id', $lodge->id)->firstOrFail();
        $service->commit($occurrence, $position, $request->user(), $request->user());

        return back();
    }

    public function withdraw(Request $request, Lodge $lodge, EventOccurrence $occurrence, EventVolunteerCommitment $commitment, VolunteerCommitmentService $service)
    {
        abort_unless($occurrence->lodge_id === $lodge->id && $commitment->event_occurrence_id === $occurrence->id && $commitment->lodge_id === $lodge->id, 404);
        $service->withdraw($commitment, $request->user());

        return back();
    }
}
