<?php

namespace App\Http\Controllers;

use App\Enums\EventOccurrenceStatus;
use App\Enums\EventReservationStatus;
use App\Enums\ReminderDeliveryStatus;
use App\Enums\VolunteerCommitmentStatus;
use App\Enums\VolunteerReminderDeliveryStatus;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Lodge;
use App\Models\Membership;
use App\Models\Person;
use App\Notifications\EventOccurrenceCancelled;
use App\Services\Audit;
use App\Services\WebsiteHtmlSanitizer;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class EventOccurrenceController extends Controller
{
    public function index(Lodge $lodge, Event $event)
    {
        $this->allow($lodge, $event);

        $occurrences = $event->occurrences()->with([
            'reservations' => fn($query) => $query->where('status', EventReservationStatus::Confirmed),
            'volunteerCommitments.position',
            'volunteerCommitments.person',
            'volunteerCommitments.reminderDelivery',
        ])->orderBy('starts_at')->paginate(50);
        $positions = $event->volunteerPositions()->where('is_active', true)->get();
        $commitments = $event->volunteerCommitments()->whereIn('event_occurrence_id', $occurrences->pluck('id'))->where('status', VolunteerCommitmentStatus::Committed)->get()->groupBy('event_occurrence_id');
        $occurrences->setCollection($occurrences->getCollection()->map(function (EventOccurrence $occurrence) use ($event, $positions, $commitments) {
            $applicable = $positions->filter(fn($position) => $position->event_occurrence_id === null || $position->event_occurrence_id === $occurrence->id);
            $filled = $commitments->get($occurrence->id, collect());

            return [
                'id' => $occurrence->id,
                'starts_at' => $occurrence->starts_at,
                'status' => $occurrence->status,
                'reservation_count' => $event->reservations_enabled ? $occurrence->reservations->count() : null,
                'reservation_roster' => $event->reservations_enabled ? $occurrence->reservations->map(fn($reservation) => ['name' => $reservation->name, 'email' => $reservation->email, 'phone' => $reservation->phone, 'party_size' => $reservation->party_size, 'status' => $reservation->status->value])->values() : [],
                'volunteer_filled' => $filled->count(),
                'volunteer_needed' => $applicable->sum('needed_count'),
                'volunteer_positions' => $applicable->map(fn($position) => ['id' => $position->id, 'name' => $position->name, 'needed_count' => $position->needed_count, 'is_active' => $position->is_active, 'commitments' => $occurrence->volunteerCommitments->where('event_volunteer_position_id', $position->id)->map(fn($commitment) => ['id' => $commitment->id, 'status' => $commitment->status->value, 'name' => $commitment->person?->display_name, 'reminder' => $commitment->reminderDelivery ? ['id' => $commitment->reminderDelivery->id, 'status' => $commitment->reminderDelivery->status->value, 'last_error' => $commitment->reminderDelivery->last_error] : null])->values()])->values(),
            ];
        }));

        return Inertia::render('events/Occurrences', [
            'lodge' => $lodge->only('id', 'name', 'timezone'),
            'event' => $event->only('id', 'title'),
            'occurrences' => $occurrences,
            'members' => Membership::query()->with('person.user')->where('lodge_id', $lodge->id)->whereNull('end_date')->whereHas('status', fn($query) => $query->where('key', 'active'))->get()->map(fn(Membership $membership) => $membership->person)->filter(fn(?Person $person) => $person?->user)->unique('id')->map(fn(Person $person) => ['id' => $person->id, 'display_name' => $person->display_name])->values(),
        ]);
    }

    public function update(Request $request, Lodge $lodge, Event $event, EventOccurrence $occurrence, WebsiteHtmlSanitizer $sanitizer)
    {
        $this->allowOccurrence($lodge, $event, $occurrence);
        $data = $request->validate([
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'title_override' => ['nullable', 'string', 'max:255'], 'description_override' => ['nullable', 'string'],
            'location_name_override' => ['nullable', 'string', 'max:255'], 'location_details_override' => ['nullable', 'string'],
            'contact_name_override' => ['nullable', 'string', 'max:255'], 'contact_email_override' => ['nullable', 'email', 'max:255'], 'contact_phone_override' => ['nullable', 'string', 'max:40'],
        ]);
        if (array_key_exists('description_override', $data)) {
            $data['description_override'] = $sanitizer->sanitize($data['description_override'] ?? '');
        }
        foreach (['starts_at', 'ends_at'] as $field) {
            if (filled($data[$field] ?? null)) {
                $data[$field] = CarbonImmutable::parse($data[$field], $event->time_zone)->utc();
            }
        }
        $before = $occurrence->toArray();
        $occurrence->fill($data + ['overridden_at' => now()])->save();
        if (array_key_exists('starts_at', $data)) {
            $occurrence->volunteerReminderDeliveries()->where('status', VolunteerReminderDeliveryStatus::Pending)->update(['due_at' => $occurrence->starts_at->copy()->subMinutes(max(1, (int)config('events.volunteer_reminder_offset_minutes', 1440)))]);
        }
        Audit::record('event_occurrence.updated', $occurrence, $lodge, $before, $occurrence->fresh()->toArray());

        return back()->with('notice', 'Occurrence updated.');
    }

    public function cancel(Lodge $lodge, Event $event, EventOccurrence $occurrence)
    {
        $this->allowOccurrence($lodge, $event, $occurrence);
        $before = $occurrence->toArray();
        $occurrence->update(['status' => EventOccurrenceStatus::Cancelled, 'cancelled_at' => now()]);
        $occurrence->reservations()->where('status', EventReservationStatus::Confirmed)->update(['status' => EventReservationStatus::EventCancelled, 'cancelled_at' => now()]);
        $occurrence->reminderDeliveries()->whereIn('status', [ReminderDeliveryStatus::Pending, ReminderDeliveryStatus::Claimed])->update(['status' => ReminderDeliveryStatus::Skipped, 'skipped_at' => now()]);
        $occurrence->volunteerReminderDeliveries()->whereIn('status', [VolunteerReminderDeliveryStatus::Pending, VolunteerReminderDeliveryStatus::Claimed])->update(['status' => VolunteerReminderDeliveryStatus::Skipped, 'skip_reason' => 'occurrence_cancelled', 'skipped_at' => now()]);
        Audit::record('event_occurrence.cancelled', $occurrence, $lodge, $before, $occurrence->fresh()->toArray());
        $this->sendCancellationNotices($occurrence);

        return back()->with('notice', 'Occurrence cancelled.');
    }

    public function restore(Lodge $lodge, Event $event, EventOccurrence $occurrence)
    {
        $this->allowOccurrence($lodge, $event, $occurrence);
        $before = $occurrence->toArray();
        $occurrence->update(['status' => EventOccurrenceStatus::Scheduled, 'cancelled_at' => null]);
        if ($occurrence->starts_at->isFuture()) {
            $occurrence->volunteerReminderDeliveries()->where('status', VolunteerReminderDeliveryStatus::Skipped)->where('skip_reason', 'occurrence_cancelled')->whereHas('commitment', fn($query) => $query->where('status', 'committed'))->whereHas('position', fn($query) => $query->where('is_active', true))->update(['status' => VolunteerReminderDeliveryStatus::Pending, 'skip_reason' => null, 'skipped_at' => null, 'due_at' => $occurrence->starts_at->copy()->subMinutes(max(1, (int)config('events.volunteer_reminder_offset_minutes', 1440)))]);
        }
        Audit::record('event_occurrence.restored', $occurrence, $lodge, $before, $occurrence->fresh()->toArray());

        return back()->with('notice', 'Occurrence restored.');
    }

    private function allow(Lodge $lodge, Event $event): void
    {
        abort_unless($event->lodge_id === $lodge->id, 404);
        abort_unless(request()->user()?->hasLodgePermission($lodge, 'events.manage'), 403);
    }

    private function allowOccurrence(Lodge $lodge, Event $event, EventOccurrence $occurrence): void
    {
        $this->allow($lodge, $event);
        abort_unless($occurrence->event_id === $event->id && $occurrence->lodge_id === $lodge->id, 404);
    }

    private function sendCancellationNotices(EventOccurrence $occurrence): void
    {
        $occurrence->loadMissing('event');
        $recipients = $occurrence->reservations()->where('status', EventReservationStatus::EventCancelled)->get(['name', 'email', 'normalized_email'])
            ->concat($occurrence->reminderSubscriptions()->where('status', 'active')->get(['name', 'email', 'normalized_email']))
            ->unique('normalized_email');
        foreach ($recipients as $recipient) {
            Notification::route('mail', [$recipient->email => $recipient->name])->notify(new EventOccurrenceCancelled($occurrence));
        }
    }
}
