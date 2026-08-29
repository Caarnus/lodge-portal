<?php

namespace App\Services;

use App\Domain\Directory\DirectoryAccess;
use App\Domain\Ritual\RitualProgress;
use App\Domain\Ritual\RitualAssistanceAccess;
use App\Enums\EventOccurrenceStatus;
use App\Enums\EventReservationStatus;
use App\Enums\EventStatus;
use App\Enums\LodgeStatus;
use App\Enums\ReminderSubscriptionStatus;
use App\Enums\VolunteerCommitmentStatus;
use App\Models\EventOccurrence;
use App\Models\EventReminderSubscription;
use App\Models\EventReservation;
use App\Models\EventVolunteerCommitment;
use App\Models\Membership;
use App\Models\User;

class DashboardService
{
    public function __construct(private readonly DirectoryAccess $directory, private readonly RitualProgress $ritual, private readonly RitualAssistanceAccess $assistance)
    {
    }

    public function read(User $user): array
    {
        $personId = $user->person_id;
        $memberships = $personId ? Membership::query()->with(['lodge', 'type', 'status', 'degree'])
            ->where('person_id', $personId)->whereNull('end_date')
            ->whereHas('status', fn($q) => $q->where('key', 'active'))
            ->whereHas('lodge', fn($q) => $q->where('status', LodgeStatus::Active))
            ->orderBy('lodge_id')->limit(12)->get() : collect();
        $lodgeIds = $memberships->pluck('lodge_id');

        return [
            'memberships' => $memberships->map(fn(Membership $m) => [
                'id' => $m->id,
                'lodge' => $m->lodge->name,
                'number' => $m->lodge->number,
                'type' => $m->type?->name,
                'degree' => $m->degree?->name,
                'site_url' => route('public.website.home', $m->lodge->slug),
                'directory_url' => $this->directory->canBrowse($user, $m->lodge)
                    ? route('lodges.directory.index', $m->lodge)
                    : null,
                'ritual_assistance_url' => $this->assistance->canBrowse($user, $m->lodge)
                    ? route('lodges.ritual-assistance.index', $m->lodge)
                    : null,
                'newsletters_url' => route('lodges.newsletters.archive', $m->lodge),
            ])->values(),
            'upcomingEvents' => EventOccurrence::query()->with(['event', 'lodge'])->whereIn('lodge_id', $lodgeIds)
                ->where('status', EventOccurrenceStatus::Scheduled)->where('starts_at', '>', now())
                ->whereHas('event', fn($q) => $q->where('status', EventStatus::Published))->orderBy('starts_at')->limit(5)->get()
                ->map(fn($o) => $this->occurrence($o))->values(),
            'reservations' => $this->owned(EventReservation::query()->with(['occurrence.event', 'lodge']), $user, $personId)
                ->where('status', EventReservationStatus::Confirmed)->whereHas('occurrence', fn($q) => $q->where('starts_at', '>', now())->where('status', EventOccurrenceStatus::Scheduled))
                ->orderByDesc('id')->limit(5)->get()->map(fn($r) => ['id' => $r->id, 'event' => $r->event?->title, 'lodge' => $r->lodge?->name])->values(),
            'reminders' => $this->owned(EventReminderSubscription::query()->with(['event', 'lodge']), $user, $personId)
                ->where('status', ReminderSubscriptionStatus::Active)->orderByDesc('id')->limit(5)->get()->map(fn($r) => ['id' => $r->id, 'event' => $r->event?->title, 'lodge' => $r->lodge?->name])->values(),
            'volunteerCommitments' => $this->owned(EventVolunteerCommitment::query()->with(['position', 'occurrence.event', 'lodge']), $user, $personId)
                ->where('status', VolunteerCommitmentStatus::Committed)->whereHas('occurrence', fn($q) => $q->where('starts_at', '>', now())->where('status', EventOccurrenceStatus::Scheduled))
                ->orderByDesc('id')->limit(5)->get()->map(fn($c) => ['id' => $c->id, 'position' => $c->position?->name, 'event' => $c->event?->title, 'lodge' => $c->lodge?->name, 'lodge_slug' => $c->lodge?->slug, 'occurrence_id' => $c->event_occurrence_id])->values(),
            'profile' => ['linked' => (bool)$personId, 'directory_scope' => $user->person?->directoryPrivacySetting?->scope?->value, 'settings_url' => route('profile.edit'), 'ritual_url' => route('ritual.index')],
            'ritual' => $this->ritualSummary($user),
        ];
    }

    private function owned($query, User $user, ?int $personId)
    {
        return $personId ? $query->where('user_id', $user->id)->where('person_id', $personId) : $query->whereRaw('1 = 0');
    }

    private function occurrence(EventOccurrence $occurrence): array
    {
        return ['id' => $occurrence->id, 'event' => $occurrence->event->title, 'lodge' => $occurrence->lodge->name, 'starts_at' => $occurrence->starts_at, 'time_zone' => $occurrence->event->time_zone, 'url' => route('public.events.show', [$occurrence->lodge->slug, $occurrence->id])];
    }

    private function ritualSummary(User $user): ?array
    {
        $person = $user->person;
        if (!$person || $person->trashed() || $person->is_deceased || $person->merged_at) return null;

        $projection = $this->ritual->projection($person);
        $active = $person->ritualProficiencies()->whereHas('part', fn ($part) => $part->where('is_active', true)->whereHas('category', fn ($category) => $category->where('is_active', true)));

        return [
            'current_total' => $projection['current_total'],
            'highest_level' => $projection['highest_achievement']?->level_name_snapshot,
            'learning_count' => (clone $active)->where('status', 'learning')->count(),
            'proficient_count' => (clone $active)->where('status', 'proficient')->count(),
            'credited_count' => (clone $active)->where('performed_for_credit', true)->count(),
            'url' => route('ritual.index'),
        ];
    }
}
