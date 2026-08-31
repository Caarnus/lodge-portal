<?php

namespace App\Services;

use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\LodgeStatus;
use App\Models\EventOccurrence;
use App\Models\Lodge;
use App\Models\LodgeGroup;
use App\Models\LodgeGroupType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PublicLodgeDirectory
{
    /** @param array{group?: string|null, group_type?: string|null, query?: string|null, city?: string|null} $filters */
    public function paginate(array $filters, int $perPage = 24): LengthAwarePaginator
    {
        $query = $this->baseQuery();
        $this->applyFilters($query, $filters);

        return $query->orderByRaw('lower(name)')->orderBy('number')->orderBy('id')
            ->paginate(min(max($perPage, 1), 50))->through(fn(Lodge $lodge) => $this->lodgeCard($lodge));
    }

    /** @return Builder<Lodge> */
    private function baseQuery(): Builder
    {
        return Lodge::query()->where('status', LodgeStatus::Active)
            ->with(['lodgeGroups' => fn($query) => $query->discoverable()->whereHas('type', fn($type) => $type->where('is_active', true))->orderBy('name')])
            ->withExists(['websitePages as has_published_homepage' => fn(Builder $pages) => $pages
                ->whereHas('published', fn(Builder $versions) => $versions->where('is_home', true))]);
    }

    /** @param Builder<Lodge> $query @param array{group?: string|null, group_type?: string|null, query?: string|null, city?: string|null} $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (filled($filters['group'] ?? null)) {
            $group = LodgeGroup::query()->discoverable()
                ->whereHas('type', fn(Builder $type) => $type->where('is_active', true))
                ->where(fn(Builder $groups) => $groups->where('slug', $filters['group'])->orWhere('id', is_numeric($filters['group']) ? (int)$filters['group'] : 0))
                ->first();
            if (!$group) {
                throw ValidationException::withMessages(['group' => 'Select a public lodge group.']);
            }
            $query->whereHas('lodgeGroups', fn(Builder $groups) => $groups->whereKey($group->id));
        }
        if (filled($filters['group_type'] ?? null)) {
            $type = LodgeGroupType::query()->active()
                ->where(fn(Builder $types) => $types->where('key', $filters['group_type'])->orWhere('id', is_numeric($filters['group_type']) ? (int)$filters['group_type'] : 0))
                ->first();
            if (!$type) {
                throw ValidationException::withMessages(['group_type' => 'Select an active group type.']);
            }
            $query->whereHas('lodgeGroups', fn(Builder $groups) => $groups->discoverable()->where('lodge_group_type_id', $type->id));
        }
        if (filled($filters['query'] ?? null)) {
            $term = trim((string)$filters['query']);
            $query->where(fn(Builder $lodges) => $lodges->whereRaw('lower(name) like ?', ['%' . mb_strtolower($term) . '%'])
                ->orWhereRaw('lower(number) like ?', ['%' . mb_strtolower($term) . '%']));
        }
        if (filled($filters['city'] ?? null)) {
            $query->whereRaw('lower(city) = ?', [mb_strtolower(trim((string)$filters['city']))]);
        }
    }

    /** @return array<string, mixed> */
    private function lodgeCard(Lodge $lodge): array
    {
        $groups = $lodge->lodgeGroups->map(fn(LodgeGroup $group) => [
            'id' => $group->id,
            'name' => $group->name,
            'slug' => $group->slug,
        ])->values();
        $logoPath = $lodge->logo_path ?? $lodge->seal_path;

        return [
            'id' => $lodge->id,
            'name' => $lodge->name,
            'number' => $lodge->number,
            'slug' => $lodge->slug,
            'city' => $lodge->city,
            'state' => $lodge->state,
            'jurisdiction' => $lodge->jurisdiction,
            'meeting_location' => $lodge->meeting_location,
            'meeting_schedule' => $lodge->meeting_schedule,
            'public_email' => $lodge->public_email,
            'public_phone' => $lodge->public_phone,
            'logo_url' => $logoPath ? Storage::disk('public')->url($logoPath) : null,
            'homepage_url' => $lodge->has_published_homepage ? "/l/{$lodge->slug}" : null,
            'groups' => $groups,
        ];
    }

    /** @param list<int> $lodgeIds */
    public function cardsFor(array $lodgeIds): array
    {
        if ($lodgeIds === []) {
            return [];
        }

        $cards = $this->baseQuery()->whereIn('lodges.id', $lodgeIds)->orderByRaw('lower(name)')->orderBy('number')->orderBy('id')
            ->get()->map(fn(Lodge $lodge) => $this->lodgeCard($lodge));

        return $cards->all();
    }

    public function publicGroup(string $slug): LodgeGroup
    {
        return LodgeGroup::query()->discoverable()->where('slug', $slug)
            ->whereHas('type', fn(Builder $query) => $query->where('is_active', true))
            ->with('type:id,key,name')->firstOrFail();
    }

    /** @return list<array<string, mixed>> */
    public function publicGroups(): array
    {
        return LodgeGroup::query()->discoverable()->whereHas('type', fn(Builder $query) => $query->where('is_active', true))
            ->orderBy('name')->get(['id', 'name', 'slug', 'lodge_group_type_id'])->map(fn(LodgeGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    public function publicGroupTypes(): array
    {
        return LodgeGroupType::query()->active()->whereHas('groups', fn(Builder $groups) => $groups->discoverable())
            ->orderBy('sort_order')->orderBy('name')->get(['id', 'key', 'name'])->map(fn(LodgeGroupType $type) => [
                'id' => $type->id,
                'key' => $type->key,
                'name' => $type->name,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    public function upcomingPublicEvents(LodgeGroup $group): array
    {
        return EventOccurrence::query()->with(['event', 'lodge:id,name,number,slug'])
            ->where('status', EventOccurrenceStatus::Scheduled)
            ->where('starts_at', '>=', now())
            ->whereHas('lodge', fn(Builder $query) => $query->where('status', LodgeStatus::Active)
                ->whereHas('lodgeGroups', fn(Builder $groups) => $groups->whereKey($group->id)))
            ->whereHas('event', fn(Builder $query) => $query->where('status', EventStatus::Published)->where('visibility', 'public'))
            ->orderBy('starts_at')->limit(12)->get()->map(fn(EventOccurrence $occurrence) => [
                'id' => $occurrence->id,
                'title' => $occurrence->title_override ?: $occurrence->event->title,
                'starts_at' => $occurrence->starts_at,
                'ends_at' => $occurrence->ends_at,
                'location_name' => $occurrence->location_name_override ?: $occurrence->event->location_name,
                'lodge' => [
                    'name' => $occurrence->lodge->name,
                    'number' => $occurrence->lodge->number,
                    'slug' => $occurrence->lodge->slug,
                ],
                'url' => "/l/{$occurrence->lodge->slug}/events/{$occurrence->id}",
            ])->all();
    }
}
