<?php

namespace App\Http\Controllers;

use App\Domain\Events\EventOccurrenceMaterializer;
use App\Domain\Events\EventScheduleReconciler;
use App\Domain\Events\RecurrenceExpander;
use App\Enums\EventReservationStatus;
use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Enums\VolunteerCommitmentStatus;
use App\Http\Requests\EventRequest;
use App\Models\Event;
use App\Models\Lodge;
use App\Models\MediaAsset;
use App\Models\Membership;
use App\Models\Person;
use App\Services\Audit;
use App\Services\WebsiteHtmlSanitizer;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EventController extends Controller
{
    public function index(Request $request, Lodge $lodge)
    {
        $this->allow($lodge);
        $events = $lodge->events()->with([
            'category',
            'occurrences.reservations',
            'occurrences.volunteerCommitments.position',
            'occurrences.volunteerCommitments.person',
            'occurrences.volunteerCommitments.reminderDelivery',
        ])->when($request->string('search')->toString(), fn($query, string $search) => $query->where('title', 'like', "%{$search}%"))
            ->orderByDesc('first_starts_at')->paginate(20)->withQueryString();
        $events->through(function (Event $event) {
            $occurrence = $event->occurrences->count() === 1 ? $event->occurrences->first() : null;
            $reservations = $occurrence?->reservations->where('status', EventReservationStatus::Confirmed) ?? collect();
            $commitments = $occurrence?->volunteerCommitments->where('status', VolunteerCommitmentStatus::Committed) ?? collect();

            return [
                'id' => $event->id, 'title' => $event->title, 'slug' => $event->slug, 'status' => $event->status,
                'first_starts_at' => $event->first_starts_at, 'category' => $event->category,
                'occurrence_count' => $event->occurrences->count(),
                'occurrence' => $event->occurrences->count() === 1 ? [
                    'id' => $occurrence->id,
                    'reservation_count' => $event->reservations_enabled ? $reservations->count() : null,
                    'reservation_roster' => $reservations->map(fn($reservation) => ['name' => $reservation->name, 'email' => $reservation->email, 'phone' => $reservation->phone, 'party_size' => $reservation->party_size, 'status' => $reservation->status->value])->values(),
                    'volunteer_filled' => $commitments->count(),
                    'volunteer_needed' => $event->volunteerPositions()->where('is_active', true)->where(fn($query) => $query->whereNull('event_occurrence_id')->orWhere('event_occurrence_id', $occurrence->id))->sum('needed_count'),
                    'volunteer_positions' => $event->volunteerPositions()->where('is_active', true)->where(fn($query) => $query->whereNull('event_occurrence_id')->orWhere('event_occurrence_id', $occurrence->id))->orderBy('sort_order')->orderBy('name')->get()->map(fn($position) => ['id' => $position->id, 'name' => $position->name, 'needed_count' => $position->needed_count, 'is_active' => $position->is_active, 'commitments' => $occurrence->volunteerCommitments->where('event_volunteer_position_id', $position->id)->map(fn($commitment) => ['id' => $commitment->id, 'status' => $commitment->status->value, 'name' => $commitment->person?->display_name, 'reminder' => $commitment->reminderDelivery ? ['id' => $commitment->reminderDelivery->id, 'status' => $commitment->reminderDelivery->status->value, 'last_error' => $commitment->reminderDelivery->last_error] : null])->values()])->values(),
                ] : null,
            ];
        });

        return Inertia::render('events/Index', ['lodge' => $lodge->only('id', 'name'), 'events' => $events, 'members' => Membership::query()->with('person.user')->where('lodge_id', $lodge->id)->whereNull('end_date')->whereHas('status', fn($query) => $query->where('key', 'active'))->get()->map(fn(Membership $membership) => $membership->person)->filter(fn(?Person $person) => $person?->user)->unique('id')->map(fn(Person $person) => ['id' => $person->id, 'display_name' => $person->display_name])->values()]);
    }

    public function create(Lodge $lodge)
    {
        $this->allow($lodge);

        return $this->editor($lodge, new Event([
            'time_zone' => $lodge->timezone,
            'first_starts_at' => now()->addHour()->startOfHour(),
            'visibility' => EventVisibility::Public->value,
            'reminders_enabled' => true,
            'guest_reminders_enabled' => true,
        ]));
    }

    public function store(EventRequest $request, Lodge $lodge, RecurrenceExpander $recurrence, EventOccurrenceMaterializer $materializer, WebsiteHtmlSanitizer $sanitizer)
    {
        $this->allow($lodge);
        $data = $this->data($request, $lodge, $recurrence, $sanitizer);
        unset($data['confirm_schedule_change']);
        $event = Event::create($data + ['lodge_id' => $lodge->id, 'status' => EventStatus::Draft, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        $event->reminderRules()->createMany([
            ['lodge_id' => $lodge->id, 'offset_minutes' => 10080],
            ['lodge_id' => $lodge->id, 'offset_minutes' => 1440],
            ['lodge_id' => $lodge->id, 'offset_minutes' => 60],
        ]);
        $this->materialize($event, $materializer);
        Audit::record('event.created', $event, $lodge, null, $event->fresh()->toArray());

        return redirect()->route('lodges.events.edit', [$lodge, $event]);
    }

    public function edit(Lodge $lodge, Event $event)
    {
        $this->allowEvent($lodge, $event);

        return $this->editor($lodge, $event);
    }

    public function update(EventRequest $request, Lodge $lodge, Event $event, RecurrenceExpander $recurrence, EventOccurrenceMaterializer $materializer, EventScheduleReconciler $reconciler, WebsiteHtmlSanitizer $sanitizer)
    {
        $this->allowEvent($lodge, $event);
        $before = $event->toArray();
        $data = $this->data($request, $lodge, $recurrence, $sanitizer);
        $scheduleChanged = $this->scheduleChanged($event, $data);
        if ($scheduleChanged && !$request->boolean('confirm_schedule_change')) {
            $protected = $event->occurrences()->where('starts_at', '>=', now())->where(fn($query) => $query->whereNotNull('overridden_at')->orWhere('status', 'cancelled')->orWhereHas('reservations')->orWhereHas('reminderSubscriptions')->orWhereHas('reminderDeliveries')->orWhereHas('volunteerPositions')->orWhereHas('volunteerCommitments')->orWhereHas('volunteerReminderDeliveries'))->count();
            throw ValidationException::withMessages(['confirm_schedule_change' => "Schedule change requires confirmation. {$protected} protected future occurrence(s) will be preserved."]);
        }
        if (filled($data['capacity'] ?? null)) {
            $largestConfirmedParty = $event->reservations()
                ->where('status', 'confirmed')
                ->selectRaw('event_occurrence_id, sum(party_size) as reserved')
                ->groupBy('event_occurrence_id')
                ->orderByDesc('reserved')
                ->value('reserved') ?? 0;
            if ($data['capacity'] < $largestConfirmedParty) {
                throw ValidationException::withMessages(['capacity' => 'Capacity cannot be lower than confirmed reservations.']);
            }
        }
        unset($data['confirm_schedule_change']);
        DB::transaction(function () use ($event, $data, $request, $scheduleChanged, $materializer, $reconciler): void {
            $event->update($data + ['updated_by' => $request->user()->id]);
            $fresh = $event->fresh();
            if ($scheduleChanged) {
                $reconciler->reconcile($fresh, now()->subMonths(3)->toImmutable(), now()->addMonths(18)->toImmutable());
            } else {
                $this->materialize($fresh, $materializer);
            }
        });
        Audit::record('event.updated', $event, $lodge, $before, $event->fresh()->toArray());

        return back()->with('notice', 'Event saved.');
    }

    public function publish(Lodge $lodge, Event $event)
    {
        $this->allowEvent($lodge, $event);
        $before = $event->toArray();
        $event->update(['status' => EventStatus::Published, 'published_at' => now()]);
        Audit::record('event.published', $event, $lodge, $before, $event->fresh()->toArray());

        return back()->with('notice', 'Event published.');
    }

    public function cancel(Lodge $lodge, Event $event)
    {
        $this->allowEvent($lodge, $event);
        $before = $event->toArray();
        DB::transaction(function () use ($event): void {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->update(['status' => EventStatus::Cancelled, 'cancelled_at' => now()]);
            $futureOccurrences = $event->occurrences()->where('starts_at', '>=', now())->pluck('id');
            $event->reservations()->whereIn('event_occurrence_id', $futureOccurrences)->where('status', 'confirmed')
                ->update(['status' => 'event_cancelled', 'cancelled_at' => now()]);
            $event->occurrences()->whereIn('id', $futureOccurrences)->each(fn($occurrence) => $occurrence->reminderDeliveries()
                ->whereIn('status', ['pending', 'claimed'])->update(['status' => 'skipped', 'skipped_at' => now()]));
            $event->occurrences()->whereIn('id', $futureOccurrences)->each(fn($occurrence) => $occurrence->volunteerReminderDeliveries()
                ->whereIn('status', ['pending', 'claimed'])->update(['status' => 'skipped', 'skip_reason' => 'event_inactive', 'skipped_at' => now()]));
        });
        Audit::record('event.cancelled', $event, $lodge, $before, $event->fresh()->toArray());

        return back()->with('notice', 'Event cancelled.');
    }

    public function archive(Lodge $lodge, Event $event)
    {
        $this->allowEvent($lodge, $event);
        if ($event->occurrences()->where('status', 'scheduled')->where('starts_at', '>', now())->exists()) {
            throw ValidationException::withMessages(['event' => 'Cancel future occurrences before archiving this event.']);
        }
        $before = $event->toArray();
        $event->update(['status' => EventStatus::Archived, 'archived_at' => now()]);
        Audit::record('event.archived', $event, $lodge, $before, $event->fresh()->toArray());

        return back()->with('notice', 'Event archived.');
    }

    private function editor(Lodge $lodge, Event $event)
    {
        return Inertia::render('events/Edit', [
            'lodge' => $lodge->only('id', 'name', 'timezone'),
            'event' => $event,
            'reservationFields' => $event->exists ? $event->reservationFields()->get() : [],
            'reminderRules' => $event->exists ? $event->reminderRules()->get(['id', 'offset_minutes']) : [],
            'reminderSubscriptionCount' => $event->exists ? $event->reminderSubscriptions()->where('status', 'active')->count() : 0,
            'volunteerPositions' => $event->exists ? $event->volunteerPositions()->orderBy('sort_order')->orderBy('name')->get(['id', 'event_occurrence_id', 'name', 'description', 'needed_count', 'sort_order', 'is_active']) : [],
            'occurrences' => $event->exists ? $event->occurrences()->where('starts_at', '>=', now())->orderBy('starts_at')->limit(50)->get(['id', 'starts_at', 'status']) : [],
            'categories' => $lodge->eventCategories()->where('is_active', true)->orderBy('sort_order')->get(['event_categories.id', 'event_categories.name']),
            'media' => MediaAsset::query()->where('lodge_id', $lodge->id)->where('processing_status', 'ready')->get(['id', 'original_name', 'derivative_path']),
        ]);
    }

    private function data(EventRequest $request, Lodge $lodge, RecurrenceExpander $recurrence, WebsiteHtmlSanitizer $sanitizer): array
    {
        $data = $request->validated();
        foreach (['allows_cross_lodge_reservations', 'reservations_enabled', 'guest_reservations_enabled', 'reminders_enabled', 'guest_reminders_enabled'] as $field) {
            $data[$field] = $request->boolean($field);
        }
        if ($data['reservations_enabled'] && empty($data['capacity'])) {
            throw ValidationException::withMessages(['capacity' => 'A positive capacity is required when reservations are enabled.']);
        }
        if (($data['maximum_party_size'] ?? null) > ($data['capacity'] ?? PHP_INT_MAX)) {
            throw ValidationException::withMessages(['maximum_party_size' => 'Maximum party size cannot exceed capacity.']);
        }
        if ($data['guest_reservations_enabled'] && $data['visibility'] !== EventVisibility::Public->value) {
            throw ValidationException::withMessages(['guest_reservations_enabled' => 'Guest reservations are available only for public events.']);
        }
        if ($data['guest_reminders_enabled'] && $data['visibility'] !== EventVisibility::Public->value) {
            throw ValidationException::withMessages(['guest_reminders_enabled' => 'Guest reminders are available only for public events.']);
        }
        if ($data['allows_cross_lodge_reservations'] && $data['visibility'] !== EventVisibility::Masons->value) {
            throw ValidationException::withMessages(['allows_cross_lodge_reservations' => 'Cross-lodge reservations are available only for Masons-only events.']);
        }
        if ($data['visibility'] !== EventVisibility::Public->value) {
            $data['required_qualification'] ??= 'ea';
        } else {
            $data['required_qualification'] = null;
        }
        $data['description'] = $sanitizer->sanitize($data['description'] ?? '');
        $data['first_starts_at'] = CarbonImmutable::parse($data['first_starts_at'], $data['time_zone'])->utc();
        $data['rrule'] = filled($data['rrule'] ?? null) ? $recurrence->canonicalize($data['rrule'], $data['first_starts_at'], $data['time_zone']) : null;
        if ($data['cover_media_asset_id'] ?? null) {
            $asset = MediaAsset::query()->whereKey($data['cover_media_asset_id'])->where('lodge_id', $lodge->id)->where('processing_status', 'ready')->first();
            if (!$asset) {
                throw ValidationException::withMessages(['cover_media_asset_id' => 'Selected media is unavailable.']);
            }
        }

        return $data;
    }

    private function materialize(Event $event, EventOccurrenceMaterializer $materializer): void
    {
        $materializer->materialize($event, now()->subMonths(3)->toImmutable(), now()->addMonths(18)->toImmutable());
    }

    private function scheduleChanged(Event $event, array $data): bool
    {
        return collect(['first_starts_at', 'duration_minutes', 'time_zone', 'rrule'])->contains(fn(string $key) => (string)$event->getAttribute($key) !== (string)($data[$key] ?? null));
    }

    private function allow(Lodge $lodge): void
    {
        abort_unless(request()->user()?->hasLodgePermission($lodge, 'events.manage'), 403);
    }

    private function allowEvent(Lodge $lodge, Event $event): void
    {
        abort_unless($event->lodge_id === $lodge->id, 404);
        $this->allow($lodge);
    }
}
