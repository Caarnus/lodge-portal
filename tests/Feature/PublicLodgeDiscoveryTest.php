<?php

namespace Tests\Feature;

use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Enums\WebsitePageStatus;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Lodge;
use App\Models\LodgeGroup;
use App\Models\LodgeGroupType;
use App\Models\WebsitePage;
use App\Models\WebsitePageVersion;
use Database\Seeders\LodgeGroupTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicLodgeDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_directory_lists_only_active_lodges_with_safe_public_projection(): void
    {
        $active = Lodge::factory()->create(['name' => 'Alpha Lodge', 'meeting_schedule' => 'First Tuesdays at 7:00 PM']);
        $unpublished = Lodge::factory()->create(['name' => 'Beta Lodge']);
        Lodge::factory()->create(['name' => 'Disabled Lodge', 'status' => 'disabled']);
        $this->publishedHome($active);

        $this->get('/lodges')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('public/Lodges')
            ->has('lodges.data', 2)
            ->where('lodges.data.0.meeting_schedule', 'First Tuesdays at 7:00 PM')
            ->where('lodges.data.0.homepage_url', "/l/{$active->slug}")
            ->where('lodges.data.1.homepage_url', null)
            ->missing('lodges.data.0.physical_address')
            ->missing('lodges.data.0.status'));

        $this->assertSame($unpublished->id, Lodge::query()->where('name', 'Beta Lodge')->sole()->id);
    }

    public function test_public_group_filter_and_landing_page_hide_non_discoverable_groups_and_disabled_lodges(): void
    {
        $this->seed(LodgeGroupTypeSeeder::class);
        $type = LodgeGroupType::query()->where('key', 'region')->sole();
        $public = LodgeGroup::create(['lodge_group_type_id' => $type->id, 'name' => 'Southwest Indiana', 'slug' => 'southwest-indiana', 'is_active' => true, 'has_public_landing_page' => true]);
        $hidden = LodgeGroup::create(['lodge_group_type_id' => $type->id, 'name' => 'Staff Only', 'slug' => 'staff-only', 'is_active' => true, 'has_public_landing_page' => false]);
        $active = Lodge::factory()->create();
        $disabled = Lodge::factory()->create(['status' => 'disabled']);
        $public->lodges()->attach([$active->id, $disabled->id]);
        $hidden->lodges()->attach($active->id);

        $this->get('/lodges?group=southwest-indiana')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('lodges.data', 1)
            ->where('lodges.data.0.id', $active->id)
            ->has('groups', 1)
            ->where('groups.0.slug', 'southwest-indiana')
            ->where('groupTypes.0.key', 'region'));
        $this->get('/lodges?group=staff-only')->assertSessionHasErrors('group');
        $this->get('/groups/staff-only')->assertNotFound();
        $this->get('/groups/southwest-indiana')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('public/LodgeGroup')
            ->where('group.name', 'Southwest Indiana')
            ->has('lodges', 1)
            ->where('lodges.0.id', $active->id));
    }

    public function test_group_landing_page_contains_only_upcoming_public_events_from_active_member_lodges(): void
    {
        $this->seed(LodgeGroupTypeSeeder::class);
        $type = LodgeGroupType::query()->where('key', 'region')->sole();
        $group = LodgeGroup::create(['lodge_group_type_id' => $type->id, 'name' => 'Southwest Indiana', 'slug' => 'southwest-indiana', 'is_active' => true, 'has_public_landing_page' => true]);
        $lodge = Lodge::factory()->create();
        $group->lodges()->attach($lodge->id);
        $public = Event::create(['lodge_id' => $lodge->id, 'slug' => 'public-event', 'title' => 'Public Event', 'status' => EventStatus::Published, 'time_zone' => 'America/Chicago', 'first_starts_at' => now()->addDay(), 'duration_minutes' => 60, 'visibility' => EventVisibility::Public]);
        $private = Event::create(['lodge_id' => $lodge->id, 'slug' => 'private-event', 'title' => 'Private Event', 'status' => EventStatus::Published, 'time_zone' => 'America/Chicago', 'first_starts_at' => now()->addDay(), 'duration_minutes' => 60, 'visibility' => EventVisibility::Masons]);
        foreach ([$public, $private] as $event) {
            EventOccurrence::create(['event_id' => $event->id, 'lodge_id' => $lodge->id, 'recurrence_key' => $event->slug, 'original_starts_at' => now()->addDay(), 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'status' => EventOccurrenceStatus::Scheduled]);
        }

        $this->get('/groups/southwest-indiana')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('events', 1)
            ->where('events.0.title', 'Public Event')
            ->missing('events.0.description'));
    }

    private function publishedHome(Lodge $lodge): void
    {
        $page = WebsitePage::create(['lodge_id' => $lodge->id]);
        WebsitePageVersion::create([
            'lodge_id' => $lodge->id,
            'website_page_id' => $page->id,
            'status' => WebsitePageStatus::Published,
            'title' => 'Home',
            'slug' => 'home',
            'is_home' => true,
            'published_at' => now(),
        ]);
    }
}
