<?php

namespace App\Domain\Events;

use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Enums\LodgeStatus;
use App\Models\EventOccurrence;
use App\Models\Lodge;
use App\Models\LodgeGroup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventDiscovery
{
    public function __construct(private readonly EventEligibility $eligibility)
    {
    }

    /** @param array<string, mixed> $filters */
    public function paginate(?User $viewer, array $filters): LengthAwarePaginator
    {
        $protectedViewer = $this->eligibility->isEligibleViewer($viewer);
        $range = $this->range($filters);
        $query = EventOccurrence::query()->with(['event.category:id,name', 'event.coverMediaAsset', 'lodge:id,name,number,slug'])
            ->where('status', EventOccurrenceStatus::Scheduled)
            ->whereBetween('starts_at', [$range['from'], $range['to']])
            ->whereHas('lodge', fn (Builder $lodges) => $lodges->where('status', LodgeStatus::Active))
            ->whereHas('event', fn (Builder $events) => $events->where('status', EventStatus::Published))
            ->orderBy('starts_at')->orderBy('id');

        $this->applyVisibility($query, $viewer, $protectedViewer);
        $this->applyFilters($query, $filters, $protectedViewer);

        return $query->paginate(50)->through(fn (EventOccurrence $occurrence) => $this->card($occurrence));
    }

    public function isProtectedViewer(?User $viewer): bool
    {
        return $this->eligibility->isEligibleViewer($viewer);
    }

    /** @return array{from: CarbonImmutable, to: CarbonImmutable} */
    private function range(array $filters): array
    {
        $from = filled($filters['from'] ?? null) ? CarbonImmutable::parse($filters['from'])->startOfDay() : now()->toImmutable()->startOfDay();
        $to = filled($filters['to'] ?? null) ? CarbonImmutable::parse($filters['to'])->endOfDay() : $from->addDays(60)->endOfDay();
        if ($to->lt($from) || $from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['to' => 'Choose a date range of no more than 366 days.']);
        }

        return compact('from', 'to');
    }

    /** @param Builder<EventOccurrence> $query */
    private function applyVisibility(Builder $query, ?User $viewer, bool $protectedViewer): void
    {
        if (! $protectedViewer || ! $viewer) {
            $query->whereHas('event', fn (Builder $events) => $events->where('visibility', EventVisibility::Public));

            return;
        }

        $query->whereHas('event', function (Builder $events) use ($viewer): void {
            $events->where('visibility', EventVisibility::Public)->orWhere(function (Builder $protected) use ($viewer): void {
                $protected->whereIn('visibility', [EventVisibility::Masons, EventVisibility::Lodge])
                    ->whereExists(function ($memberships) use ($viewer): void {
                        $memberships->selectRaw('1')->from('memberships')
                            ->join('membership_statuses', 'membership_statuses.id', '=', 'memberships.membership_status_id')
                            ->join('lodges as membership_lodges', 'membership_lodges.id', '=', 'memberships.lodge_id')
                            ->leftJoin('masonic_degrees', 'masonic_degrees.id', '=', 'memberships.masonic_degree_id')
                            ->where('memberships.person_id', $viewer->person_id)
                            ->whereNull('memberships.end_date')
                            ->where('membership_statuses.key', 'active')
                            ->where('membership_lodges.status', LodgeStatus::Active)
                            ->where(function ($scope): void {
                                $scope->where('events.visibility', EventVisibility::Masons)
                                    ->orWhereColumn('memberships.lodge_id', 'events.lodge_id');
                            })
                            ->where(function ($qualification) use ($viewer): void {
                                $qualification->whereNull('events.required_qualification')
                                    ->orWhere(fn ($query) => $query->where('events.required_qualification', 'ea')->whereNotNull('masonic_degrees.id'))
                                    ->orWhere(fn ($query) => $query->where('events.required_qualification', 'fc')->whereIn('masonic_degrees.key', ['fellow_craft', 'master_mason']))
                                    ->orWhere(fn ($query) => $query->where('events.required_qualification', 'mm')->where('masonic_degrees.key', 'master_mason'))
                                    ->orWhere(fn ($query) => $query->where('events.required_qualification', 'pm')->where('masonic_degrees.key', 'master_mason')
                                        ->whereExists(fn ($terms) => $terms->selectRaw('1')->from('past_master_terms')->where('past_master_terms.person_id', $viewer->person_id)));
                            });
                    });
            });
        });
    }

    /** @param Builder<EventOccurrence> $query @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters, bool $protectedViewer): void
    {
        if (filled($filters['group'] ?? null)) {
            $groups = LodgeGroup::query();
            $protectedViewer ? $groups->active() : $groups->discoverable();
            $group = $groups->where(fn (Builder $items) => $items->where('slug', $filters['group'])->orWhere('id', is_numeric($filters['group']) ? (int) $filters['group'] : 0))->first();
            if (! $group) {
                throw ValidationException::withMessages(['group' => 'Select an available lodge group.']);
            }
            $query->whereHas('lodge.lodgeGroups', fn (Builder $groups) => $groups->whereKey($group->id));
        }
        if (filled($filters['lodge'] ?? null)) {
            $lodge = Lodge::query()->where('status', LodgeStatus::Active)->where(fn (Builder $lodges) => $lodges->where('slug', $filters['lodge'])->orWhere('id', is_numeric($filters['lodge']) ? (int) $filters['lodge'] : 0))->first();
            if (! $lodge) {
                throw ValidationException::withMessages(['lodge' => 'Select an active lodge.']);
            }
            $query->where('lodge_id', $lodge->id);
        }
        if (filled($filters['category'] ?? null)) {
            $query->whereHas('event.category', fn (Builder $categories) => $categories->where('is_active', true)->where('key', $filters['category']));
        }
        if (filled($filters['visibility'] ?? null)) {
            if (! $protectedViewer && $filters['visibility'] !== EventVisibility::Public->value) {
                throw ValidationException::withMessages(['visibility' => 'Sign in to filter protected events.']);
            }
            $query->whereHas('event', fn (Builder $events) => $events->where('visibility', $filters['visibility']));
        }
        if (filled($filters['qualification'] ?? null)) {
            if (! $protectedViewer) {
                throw ValidationException::withMessages(['qualification' => 'Sign in to filter protected events.']);
            }
            $query->whereHas('event', fn (Builder $events) => $events->where('required_qualification', $filters['qualification']));
        }
    }

    /** @return array<string, mixed> */
    private function card(EventOccurrence $occurrence): array
    {
        $event = $occurrence->event;

        return [
            'id' => $occurrence->id,
            'title' => $occurrence->title_override ?: $event->title,
            'starts_at' => $occurrence->starts_at,
            'ends_at' => $occurrence->ends_at,
            'time_zone' => $event->time_zone,
            'location_name' => $occurrence->location_name_override ?: $event->location_name,
            'category' => $event->category?->name,
            'visibility' => $event->visibility->value,
            'required_qualification' => $event->required_qualification?->value,
            'cover_image' => $event->coverMediaAsset?->url,
            'lodge' => ['name' => $occurrence->lodge->name, 'number' => $occurrence->lodge->number, 'slug' => $occurrence->lodge->slug],
            'url' => "/l/{$occurrence->lodge->slug}/events/{$occurrence->id}",
        ];
    }
}
