<?php

namespace App\Http\Controllers;

use App\Domain\Events\EventEligibility;
use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Enums\LodgeStatus;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Lodge;
use App\Services\ICalendarBuilder;
use Illuminate\Http\Request;

class PublicEventCalendarController extends Controller
{
    public function occurrence(Request $request, Lodge $lodge, EventOccurrence $occurrence, EventEligibility $eligibility, ICalendarBuilder $calendar)
    {
        abort_unless($lodge->status === LodgeStatus::Active && $occurrence->lodge_id === $lodge->id, 404);
        $occurrence->load('event');
        abort_unless($occurrence->status === EventOccurrenceStatus::Scheduled && $occurrence->event->status === EventStatus::Published && $eligibility->canView($request->user(), $occurrence->event), 404);

        return response($calendar->build([$occurrence]), 200, ['Content-Type' => 'text/calendar; charset=utf-8', 'Content-Disposition' => 'attachment; filename="event.ics"']);
    }

    public function feed(Lodge $lodge, ICalendarBuilder $calendar)
    {
        abort_unless($lodge->status === LodgeStatus::Active, 404);
        $occurrences = EventOccurrence::query()->with('event')->where('lodge_id', $lodge->id)->where('status', EventOccurrenceStatus::Scheduled)->where('starts_at', '>=', now())->whereHas('event', fn($query) => $query->where('status', EventStatus::Published)->where('visibility', EventVisibility::Public))->orderBy('starts_at')->get();

        return response($calendar->build($occurrences), 200, ['Content-Type' => 'text/calendar; charset=utf-8']);
    }

    public function series(Request $request, Lodge $lodge, Event $event, EventEligibility $eligibility, ICalendarBuilder $calendar)
    {
        abort_unless($lodge->status === LodgeStatus::Active && $event->lodge_id === $lodge->id && $event->rrule !== null && $event->status === EventStatus::Published && $eligibility->canView($request->user(), $event), 404);

        return response($calendar->buildSeries($event), 200, ['Content-Type' => 'text/calendar; charset=utf-8', 'Content-Disposition' => 'attachment; filename="event-series.ics"']);
    }
}
