<?php

namespace Tests\Feature;

use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Lodge;
use App\Models\LodgeGroup;
use App\Models\LodgeGroupType;
use App\Models\MasonicDegree;
use App\Models\Membership;
use App\Models\MembershipStatus;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\LodgeGroupTypeSeeder;
use Database\Seeders\PeopleMembershipReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegionalEventDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_regional_discovery_returns_only_public_events_from_active_lodges(): void
    {
        $a = Lodge::factory()->create();
        $b = Lodge::factory()->create(['status' => 'disabled']);
        $this->occurrence($a, 'Public event', EventVisibility::Public);
        $this->occurrence($a, 'Masons event', EventVisibility::Masons);
        $this->occurrence($b, 'Disabled lodge event', EventVisibility::Public);

        $this->get('/events')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('public/RegionalEvents')
            ->has('events.data', 1)
            ->where('events.data.0.title', 'Public event')
            ->where('canViewProtectedEvents', false)
            ->missing('events.data.0.description'));
    }

    public function test_eligible_member_sees_masons_events_but_not_foreign_lodge_only_events(): void
    {
        $this->seed(PeopleMembershipReferenceSeeder::class);
        $memberLodge = Lodge::factory()->create();
        $eventLodge = Lodge::factory()->create();
        $person = Person::factory()->create();
        $user = User::factory()->create(['person_id' => $person->id]);
        Membership::create([
            'lodge_id' => $memberLodge->id,
            'person_id' => $person->id,
            'membership_status_id' => MembershipStatus::query()->where('key', 'active')->sole()->id,
            'masonic_degree_id' => MasonicDegree::query()->where('key', 'entered_apprentice')->sole()->id,
        ]);
        $this->occurrence($eventLodge, 'Masons event', EventVisibility::Masons, 'ea');
        $this->occurrence($eventLodge, 'Lodge only event', EventVisibility::Lodge, 'ea');

        $this->actingAs($user)->get('/events')->assertOk()->assertHeader('cache-control', 'no-store, private')->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.title', 'Masons event')
            ->where('canViewProtectedEvents', true));
    }

    public function test_group_filter_never_broadens_regional_results(): void
    {
        $this->seed(LodgeGroupTypeSeeder::class);
        $type = LodgeGroupType::query()->where('key', 'region')->sole();
        $group = LodgeGroup::create(['lodge_group_type_id' => $type->id, 'name' => 'Southwest Indiana', 'slug' => 'southwest-indiana', 'is_active' => true, 'has_public_landing_page' => true]);
        $inside = Lodge::factory()->create();
        $outside = Lodge::factory()->create();
        $group->lodges()->attach($inside->id);
        $this->occurrence($inside, 'Inside group', EventVisibility::Public);
        $this->occurrence($outside, 'Outside group', EventVisibility::Public);

        $this->get('/events?group=southwest-indiana')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.title', 'Inside group'));
    }

    private function occurrence(Lodge $lodge, string $title, EventVisibility $visibility, ?string $qualification = null): void
    {
        $event = Event::create([
            'lodge_id' => $lodge->id,
            'slug' => str($title)->slug()->toString(),
            'title' => $title,
            'status' => EventStatus::Published,
            'time_zone' => 'America/Chicago',
            'first_starts_at' => now()->addDay(),
            'duration_minutes' => 60,
            'visibility' => $visibility,
            'required_qualification' => $qualification,
        ]);
        EventOccurrence::create([
            'event_id' => $event->id,
            'lodge_id' => $lodge->id,
            'recurrence_key' => $event->slug,
            'original_starts_at' => now()->addDay(),
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => EventOccurrenceStatus::Scheduled,
        ]);
    }
}
