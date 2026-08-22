<?php

namespace Tests\Feature;

use App\Domain\Events\VolunteerCommitmentService;
use App\Domain\Events\VolunteerReminderDispatcher;
use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Enums\VolunteerCommitmentStatus;
use App\Jobs\SendVolunteerReminderDelivery;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventVolunteerPosition;
use App\Models\EventVolunteerReminderDelivery;
use App\Models\Lodge;
use App\Models\MasonicDegree;
use App\Models\Membership;
use App\Models\MembershipStatus;
use App\Models\Person;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PeopleMembershipReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventVolunteerTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_commit_to_multiple_positions_but_not_same_position_twice(): void
    {
        [$event, $occurrence, $member] = $this->volunteerContext();
        $setup = EventVolunteerPosition::create(['event_id' => $event->id, 'lodge_id' => $event->lodge_id, 'name' => 'Setup', 'needed_count' => 1]);
        $cleanup = EventVolunteerPosition::create(['event_id' => $event->id, 'lodge_id' => $event->lodge_id, 'name' => 'Cleanup', 'needed_count' => 1]);
        $service = app(VolunteerCommitmentService::class);

        $service->commit($occurrence, $setup, $member, $member);
        $service->commit($occurrence, $cleanup, $member, $member);

        $this->assertDatabaseCount('event_volunteer_commitments', 2);
        $this->expectException(ValidationException::class);
        $service->commit($occurrence, $setup, $member, $member);
    }

    public function test_withdrawal_reopens_position_with_new_history_row(): void
    {
        [$event, $occurrence, $member] = $this->volunteerContext();
        $position = EventVolunteerPosition::create(['event_id' => $event->id, 'lodge_id' => $event->lodge_id, 'name' => 'Setup', 'needed_count' => 1]);
        $service = app(VolunteerCommitmentService::class);
        $first = $service->commit($occurrence, $position, $member, $member);

        $service->withdraw($first, $member);
        $second = $service->commit($occurrence, $position, $member, $member);

        $this->assertNotSame($first->id, $second->id);
        $this->assertDatabaseHas('event_volunteer_commitments', ['id' => $first->id, 'status' => VolunteerCommitmentStatus::Withdrawn->value]);
        $this->assertDatabaseHas('event_volunteer_commitments', ['id' => $second->id, 'status' => VolunteerCommitmentStatus::Committed->value]);
    }

    public function test_dispatcher_creates_one_staffing_reminder_delivery_and_claims_it_once(): void
    {
        Queue::fake();
        [$event, $occurrence, $member] = $this->volunteerContext();
        $occurrence->update(['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)]);
        $position = EventVolunteerPosition::create(['event_id' => $event->id, 'lodge_id' => $event->lodge_id, 'name' => 'Setup', 'needed_count' => 1]);
        app(VolunteerCommitmentService::class)->commit($occurrence, $position, $member, $member);

        $dispatcher = app(VolunteerReminderDispatcher::class);
        $this->assertSame(1, $dispatcher->dispatchDue(CarbonImmutable::now()));
        $this->assertSame(0, $dispatcher->dispatchDue(CarbonImmutable::now()));

        $this->assertDatabaseCount('event_volunteer_reminder_deliveries', 1);
        $this->assertDatabaseHas('event_volunteer_reminder_deliveries', ['status' => 'claimed']);
        Queue::assertPushed(SendVolunteerReminderDelivery::class, 1);
    }

    public function test_volunteer_factories_produce_matching_ownership_rows(): void
    {
        $delivery = EventVolunteerReminderDelivery::factory()->create();

        $this->assertDatabaseHas('event_volunteer_reminder_deliveries', ['id' => $delivery->id, 'event_id' => $delivery->commitment->event_id, 'lodge_id' => $delivery->commitment->lodge_id]);
    }

    public function test_member_commitment_route_rejects_a_position_from_another_event(): void
    {
        $this->withoutMiddleware();
        [$event, $occurrence, $member] = $this->volunteerContext();
        $foreign = EventVolunteerPosition::factory()->create();

        $this->actingAs($member)->post("/l/{$event->lodge->slug}/events/{$occurrence->id}/volunteer-commitments", ['position_id' => $foreign->id])->assertNotFound();
    }

    public function test_eligible_member_sees_unfilled_positions_on_public_event_detail(): void
    {
        [$event, $occurrence, $member] = $this->volunteerContext();
        EventVolunteerPosition::create([
            'event_id' => $event->id,
            'lodge_id' => $event->lodge_id,
            'name' => 'Setup',
            'needed_count' => 1,
        ]);

        $this->actingAs($member)
            ->get("/l/{$event->lodge->slug}/events/{$occurrence->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/EventDetail')
                ->has('staffing', 1)
                ->where('staffing.0.name', 'Setup')
                ->where('staffing.0.can_commit', true)
                ->where('staffing.0.can_withdraw', false));
    }

    /** @return array{Event, EventOccurrence, User} */
    private function volunteerContext(): array
    {
        $this->seed(PeopleMembershipReferenceSeeder::class);
        $lodge = Lodge::factory()->create(['timezone' => 'America/Chicago']);
        $person = Person::factory()->create();
        $member = User::factory()->create(['person_id' => $person->id]);
        Membership::create(['lodge_id' => $lodge->id, 'person_id' => $person->id, 'membership_status_id' => MembershipStatus::query()->where('key', 'active')->sole()->id, 'masonic_degree_id' => MasonicDegree::query()->where('key', 'entered_apprentice')->sole()->id]);
        $event = Event::create(['lodge_id' => $lodge->id, 'slug' => 'volunteer-event', 'status' => EventStatus::Published, 'title' => 'Volunteer Event', 'time_zone' => 'America/Chicago', 'first_starts_at' => now()->addWeek(), 'duration_minutes' => 60, 'visibility' => EventVisibility::Public]);
        $occurrence = EventOccurrence::create(['event_id' => $event->id, 'lodge_id' => $lodge->id, 'recurrence_key' => 'volunteer-event-1', 'original_starts_at' => now()->addWeek(), 'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHour(), 'status' => EventOccurrenceStatus::Scheduled]);

        return [$event, $occurrence, $member];
    }
}
