<?php

namespace App\Http\Controllers;

use App\Domain\Events\EventEligibility;
use App\Domain\Events\EventOccurrenceMaterializer;
use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Enums\LodgeStatus;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Lodge;
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
            'navigation' => $this->navigation($lodge),
            'occurrences' => ['data' => $occurrences->map(fn (EventOccurrence $occurrence) => $this->eventData($occurrence))->all()],
            'range' => $range['key'],
            'rangeOptions' => $this->rangeOptions(),
        ]);
    }

    public function show(Request $request, Lodge $lodge, EventOccurrence $occurrence, EventEligibility $eligibility)
    {
        $this->allowPublic($lodge);
        abort_unless($occurrence->lodge_id === $lodge->id, 404);
        $occurrence->load(['event.category', 'event.coverMediaAsset']);
        abort_unless($occurrence->status === EventOccurrenceStatus::Scheduled && $occurrence->event->status === EventStatus::Published, 404);
        abort_unless($occurrence->event->visibility === EventVisibility::Public || $eligibility->canView($request->user(), $occurrence->event), 404);

        return Inertia::render('public/EventDetail', [
            'lodge' => $lodge,
            'navigation' => $this->navigation($lodge),
            'occurrence' => $this->eventData($occurrence),
            'viewer' => $request->user()?->only('name', 'email'),
        ]);
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
        ];
    }

    private function navigation(Lodge $lodge): array
    {
        return WebsitePageVersion::query()->with('page')->where('lodge_id', $lodge->id)->where('status', 'published')
            ->where('show_in_navigation', true)->whereHas('page', fn ($query) => $query->whereNull('deleted_at'))
            ->orderBy('navigation_order')->orderBy('title')->get()->map(fn (WebsitePageVersion $version) => [
                'title' => $version->title, 'slug' => $version->slug, 'is_home' => $version->is_home, 'children' => [],
            ])->all();
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
