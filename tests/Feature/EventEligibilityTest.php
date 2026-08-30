<?php

namespace Tests\Feature;

use App\Domain\Events\EventEligibility;
use App\Enums\EventQualification;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\Lodge;
use App\Models\MasonicDegree;
use App\Models\Membership;
use App\Models\MembershipStatus;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\PeopleMembershipReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_event_requires_approved_verified_user_with_live_person(): void
    {
        $this->seed(PeopleMembershipReferenceSeeder::class);
        $lodge = Lodge::factory()->create();
        $person = Person::factory()->create();
        $user = User::factory()->create(['person_id' => $person->id]);
        $event = $this->event($lodge, EventVisibility::Masons);
        $this->membership($person, $lodge, 'master_mason');
        $eligibility = app(EventEligibility::class);

        $this->assertTrue($eligibility->canView($user, $event));

        $user->update(['approval_status' => 'pending']);
        $this->assertFalse($eligibility->canView($user->fresh(), $event));
        $user->update(['approval_status' => 'approved']);
        $user->forceFill(['email_verified_at' => null])->save();
        $this->assertFalse($eligibility->canView($user->fresh(), $event));
        $user->forceFill(['email_verified_at' => now()])->save();

        $person->update(['is_deceased' => true]);
        $this->assertFalse($eligibility->canView($user->fresh(), $event));
        $person->update(['is_deceased' => false, 'merged_at' => now()]);
        $this->assertFalse($eligibility->canView($user->fresh(), $event));
        $person->update(['merged_at' => null]);
        $person->delete();
        $this->assertFalse($eligibility->canView($user->fresh(), $event));
    }

    public function test_masons_visibility_uses_any_active_lodge_but_reservations_honor_cross_lodge_setting(): void
    {
        $this->seed(PeopleMembershipReferenceSeeder::class);
        $eventLodge = Lodge::factory()->create();
        $memberLodge = Lodge::factory()->create();
        $person = Person::factory()->create();
        $user = User::factory()->create(['person_id' => $person->id]);
        $event = $this->event($eventLodge, EventVisibility::Masons, ['allows_cross_lodge_reservations' => false]);
        $this->membership($person, $memberLodge, 'master_mason');
        $eligibility = app(EventEligibility::class);

        $this->assertTrue($eligibility->canView($user, $event));
        $this->assertFalse($eligibility->canReserve($user, $event));

        $event->update(['allows_cross_lodge_reservations' => true]);
        $this->assertTrue($eligibility->canReserve($user, $event->fresh()));

        $event->update(['visibility' => EventVisibility::Lodge]);
        $this->assertFalse($eligibility->canView($user, $event->fresh()));
        $this->assertFalse($eligibility->canReserve($user, $event->fresh()));
    }

    public function test_active_lodges_and_qualification_hierarchy_are_required_without_admin_bypass(): void
    {
        $this->seed(PeopleMembershipReferenceSeeder::class);
        $eventLodge = Lodge::factory()->create();
        $memberLodge = Lodge::factory()->create();
        $person = Person::factory()->create();
        $user = User::factory()->create(['person_id' => $person->id, 'is_platform_admin' => true]);
        $event = $this->event($eventLodge, EventVisibility::Masons, ['required_qualification' => EventQualification::MasterMason]);
        $membership = $this->membership($person, $memberLodge, 'fellow_craft');
        $eligibility = app(EventEligibility::class);

        $this->assertFalse($eligibility->canView($user, $event));
        $membership->update(['masonic_degree_id' => MasonicDegree::query()->where('key', 'master_mason')->sole()->id]);
        $this->assertTrue($eligibility->canView($user, $event));

        $event->update(['required_qualification' => EventQualification::PastMaster]);
        $this->assertFalse($eligibility->canView($user, $event->fresh()));
        $person->pastMasterTerms()->create(['lodge_id' => $memberLodge->id, 'year' => 2025]);
        $this->assertTrue($eligibility->canView($user, $event->fresh()));

        $membership->update(['end_date' => now()->toDateString()]);
        $this->assertFalse($eligibility->canView($user, $event->fresh()));
        $membership->update(['end_date' => null]);

        $memberLodge->update(['status' => 'disabled']);
        $this->assertFalse($eligibility->canView($user, $event));
        $memberLodge->update(['status' => 'active']);
        $eventLodge->update(['status' => 'disabled']);
        $this->assertFalse($eligibility->canView($user, $event));
    }

    private function event(Lodge $lodge, EventVisibility $visibility, array $attributes = []): Event
    {
        return Event::create($attributes + [
            'lodge_id' => $lodge->id,
            'slug' => fake()->unique()->slug(),
            'title' => 'Protected event',
            'time_zone' => 'America/Chicago',
            'first_starts_at' => now()->addDay(),
            'duration_minutes' => 60,
            'visibility' => $visibility,
        ]);
    }

    private function membership(Person $person, Lodge $lodge, string $degree): Membership
    {
        return Membership::create([
            'person_id' => $person->id,
            'lodge_id' => $lodge->id,
            'membership_status_id' => MembershipStatus::query()->where('key', 'active')->sole()->id,
            'masonic_degree_id' => MasonicDegree::query()->where('key', $degree)->sole()->id,
        ]);
    }
}
