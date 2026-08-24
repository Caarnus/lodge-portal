<?php

namespace App\Http\Controllers;

use App\Domain\Events\EventEligibility;
use App\Domain\Events\EventOccurrenceMaterializer;
use App\Domain\Events\VolunteerEligibility;
use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Enums\LodgeStatus;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventVolunteerCommitment;
use App\Models\Lodge;
use App\Models\Membership;
use App\Models\User;
use App\Models\WebsitePageVersion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicEventController extends Controller
{
    public function index(Request $request, Lodge $lodge, EventEligibility $eligibility, EventOccurrenceMaterializer $materializer)
    {
        $this->allowPublic($lodge);
        $range = $this->range($request);
        $now = now();
        $through = $now->copy()->addDays($range['days'])->endOfDay();

        Event::query()
            ->where('lodge_id', $lodge->id)
            ->where('status', EventStatus::Published)
            ->whereNotNull('rrule')
            ->where('first_starts_at', '<=', $through)
            ->where(fn ($query) => $query->whereNull('occurrences_generated_through')->orWhere('occurrences_generated_through', '<', $through))
            ->each(fn (Event $event) => $materializer->materialize($event, $now->toImmutable(), $through->toImmutable()));

        $occurrences = EventOccurrence::query()->with(['event.category', 'event.coverMediaAsset'])
            ->where('lodge_id', $lodge->id)->where('status', EventOccurrenceStatus::Scheduled)
            ->whereBetween('starts_at', [$now, $through])->whereHas('event', fn ($query) => $query->where('status', EventStatus::Published))
            ->orderBy('starts_at')->get()->filter(fn (EventOccurrence $occurrence) => $occurrence->event->visibility === EventVisibility::Public || $eligibility->canView($request->user(), $occurrence->event))
            ->take(20)->values();

        return Inertia::render('public/Events', [
            'lodge' => $lodge,
            'navigation' => $this->navigation($lodge, $request->user()),
            'occurrences' => ['data' => $occurrences->map(fn (EventOccurrence $occurrence) => $this->eventData($occurrence))->all()],
            'range' => $range['key'],
            'rangeOptions' => $this->rangeOptions(),
        ]);
    }

    public function show(Request $request, Lodge $lodge, EventOccurrence $occurrence, EventEligibility $eligibility, VolunteerEligibility $volunteerEligibility)
    {
        $this->allowPublic($lodge);
        abort_unless($occurrence->lodge_id === $lodge->id, 404);
        $occurrence->load(['event.category', 'event.coverMediaAsset', 'event.reservationFields']);
        abort_unless($occurrence->status === EventOccurrenceStatus::Scheduled && $occurrence->event->status === EventStatus::Published, 404);
        abort_unless($occurrence->event->visibility === EventVisibility::Public || $eligibility->canView($request->user(), $occurrence->event), 404);

        $props = [
            'lodge' => $lodge,
            'navigation' => $this->navigation($lodge, $request->user()),
            'occurrence' => $this->eventData($occurrence),
            'viewer' => $request->user()?->only('name', 'email'),
        ];
        if ($volunteerEligibility->canVolunteer($request->user(), $occurrence->event) && $occurrence->starts_at->isFuture()) {
            $positions = $occurrence->event->volunteerPositions()->where('is_active', true)->where(fn ($query) => $query->whereNull('event_occurrence_id')->orWhere('event_occurrence_id', $occurrence->id))->orderBy('sort_order')->orderBy('name')->get();
            $counts = EventVolunteerCommitment::query()->selectRaw('event_volunteer_position_id, count(*) as filled')->where('event_occurrence_id', $occurrence->id)->where('status', 'committed')->groupBy('event_volunteer_position_id')->pluck('filled', 'event_volunteer_position_id');
            $own = EventVolunteerCommitment::query()->where('event_occurrence_id', $occurrence->id)->where('user_id', $request->user()->id)->where('person_id', $request->user()->person_id)->where('status', 'committed')->get()->keyBy('event_volunteer_position_id');
            $props['staffing'] = $positions->map(function ($position) use ($counts, $own) {
                $commitment = $own->get($position->id);
                $filledCount = (int) ($counts->get($position->id, 0));

                return [
                    'id' => $position->id,
                    'name' => $position->name,
                    'description' => $position->description,
                    'needed_count' => $position->needed_count,
                    'filled_count' => $filledCount,
                    'remaining_count' => max($position->needed_count - $filledCount, 0),
                    'scope' => $position->event_occurrence_id ? 'occurrence' : 'series',
                    'commitment_id' => $commitment?->id,
                    'can_commit' => $commitment === null && $filledCount < $position->needed_count,
                    'can_withdraw' => $commitment !== null,
                ];
            })->values();
        }

        return Inertia::render('public/EventDetail', $props);
    }

    private function eventData(EventOccurrence $occurrence): array
    {
        $event = $occurrence->event;

        return [
            'id' => $occurrence->id,
            'title' => $occurrence->title_override ?: $event->title,
            'description' => $occurrence->description_override ?: $event->description,
            'starts_at' => $occurrence->starts_at,
            'ends_at' => $occurrence->ends_at,
            'time_zone' => $event->time_zone,
            'location_name' => $occurrence->location_name_override ?: $event->location_name,
            'location_details' => $occurrence->location_details_override ?: $event->location_details,
            'category' => $event->category?->name,
            'visibility' => $event->visibility->value,
            'cover_image' => $event->coverMediaAsset?->url,
            'reservations_enabled' => $event->reservations_enabled,
            'guest_reservations_enabled' => $event->guest_reservations_enabled,
            'capacity' => $event->capacity,
            'event_id' => $event->id,
            'is_recurring' => $event->rrule !== null,
            'reminders_enabled' => $event->reminders_enabled,
            'guest_reminders_enabled' => $event->guest_reminders_enabled,
            'reservation_fields' => $event->relationLoaded('reservationFields') ? $event->reservationFields->where('is_active', true)->map(fn ($field) => [
                'key' => $field->key, 'label' => $field->label, 'help_text' => $field->help_text, 'type' => $field->type->value, 'is_required' => $field->is_required, 'options' => $field->options,
            ])->values() : [],
        ];
    }

    private function navigation(Lodge $lodge, ?User $user): array
    {
        $query = WebsitePageVersion::query()->with('page')->where('lodge_id', $lodge->id)->where('status', 'published')
            ->where('show_in_navigation', true)->whereHas('page', fn ($query) => $query->whereNull('deleted_at'));

        if (! $this->isActiveMember($user)) {
            $query->where('navigation_visibility', 'public');
        } elseif (! $this->isActiveLodgeMember($user, $lodge)) {
            $query->whereIn('navigation_visibility', ['public', 'masons']);
        }

        return $query
            ->orderBy('navigation_order')->orderBy('title')->get()->map(fn (WebsitePageVersion $version) => [
                'title' => $version->title, 'slug' => $version->slug, 'is_home' => $version->is_home, 'children' => [],
            ])->all();
    }

    private function isActiveMember(?User $user): bool
    {
        $person = $user?->person;

        return $user && $user->approval_status === 'approved' && $user->hasVerifiedEmail() && $person && ! $person->trashed() && ! $person->merged_at && ! $person->is_deceased
            && Membership::query()->where('person_id', $person->id)->whereNull('end_date')->whereHas('status', fn ($query) => $query->where('key', 'active'))->exists();
    }

    private function isActiveLodgeMember(?User $user, Lodge $lodge): bool
    {
        return $user?->person && Membership::query()->where('person_id', $user->person_id)->where('lodge_id', $lodge->id)->whereNull('end_date')->whereHas('status', fn ($query) => $query->where('key', 'active'))->exists();
    }

    /** @return array{key: string, days: int} */
    private function range(Request $request): array
    {
        $key = $request->string('range')->toString() ?: '60-days';

        foreach ($this->rangeOptions() as $option) {
            if ($option['key'] === $key) {
                return ['key' => $option['key'], 'days' => $option['days']];
            }
        }

        return ['key' => '60-days', 'days' => 60];
    }

    /** @return list<array{key: string, label: string, days: int}> */
    private function rangeOptions(): array
    {
        return [
            ['key' => '7-days', 'label' => 'Next week', 'days' => 7],
            ['key' => '60-days', 'label' => 'Next 60 days', 'days' => 60],
            ['key' => '90-days', 'label' => 'Next 3 months', 'days' => 90],
            ['key' => '180-days', 'label' => 'Next 6 months', 'days' => 180],
            ['key' => '365-days', 'label' => 'Next year', 'days' => 365],
        ];
    }

    private function allowPublic(Lodge $lodge): void
    {
        abort_unless($lodge->status === LodgeStatus::Active, 404);
    }
}
