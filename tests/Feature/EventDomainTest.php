<?php

namespace Tests\Feature;

use App\Domain\Events\EventOccurrenceMaterializer;
use App\Domain\Events\EventReminderDispatcher;
use App\Domain\Events\EventReminderSubscriptionService;
use App\Domain\Events\EventReservationService;
use App\Domain\Events\RecurrenceExpander;
use App\Enums\EventOccurrenceStatus;
use App\Enums\EventReservationStatus;
use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Enums\ReminderDeliveryStatus;
use App\Enums\ReminderSubscriptionStatus;
use App\Jobs\SendEventReminderDelivery;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\EventOccurrence;
use App\Models\EventReminderDelivery;
use App\Models\EventReminderSubscription;
use App\Models\Lodge;
use App\Models\MasonicDegree;
use App\Models\Membership;
use App\Models\MembershipStatus;
use App\Models\Person;
use App\Models\User;
use App\Services\LodgeRoleCatalog;
use Carbon\CarbonImmutable;
use Database\Seeders\EventReferenceSeeder;
use Database\Seeders\PeopleMembershipReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EventDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_reference_categories_and_role_permissions_are_seedable(): void
    {
        $this->seed(EventReferenceSeeder::class);
        app(LodgeRoleCatalog::class)->seedPermissions();

        $this->assertDatabaseCount('event_categories', 8);
        $this->assertDatabaseHas('permissions', ['key' => 'events.manage']);
    }

    public function test_materializer_creates_stable_weekly_occurrences(): void
    {
        $lodge = Lodge::factory()->create(['timezone' => 'America/Chicago']);
        $category = EventCategory::create(['key' => 'meeting', 'name' => 'Meeting']);
        $event = Event::create([
            'lodge_id' => $lodge->id,
            'event_category_id' => $category->id,
            'slug' => 'stated-meeting',
            'status' => EventStatus::Draft,
            'title' => 'Stated Meeting',
            'time_zone' => 'America/Chicago',
            'first_starts_at' => CarbonImmutable::parse('2026-08-05 19:00:00', 'America/Chicago')->utc(),
            'duration_minutes' => 120,
            'rrule' => 'FREQ=WEEKLY;COUNT=3',
            'visibility' => EventVisibility::Public,
        ]);

        $occurrences = app(EventOccurrenceMaterializer::class)->materialize(
            $event,
            CarbonImmutable::parse('2026-08-01', 'America/Chicago'),
            CarbonImmutable::parse('2026-08-31 23:59:59', 'America/Chicago'),
        );

        $this->assertCount(3, $occurrences);
        $this->assertSame(['20260805T190000', '20260812T190000', '20260819T190000'], array_map(
            fn ($occurrence) => $occurrence->recurrence_key,
            $occurrences,
        ));
        $this->assertDatabaseCount('event_occurrences', 3);
    }

    public function test_recurrence_expander_reads_legacy_full_rfc_rules_and_canonicalizes_new_rules(): void
    {
        $expander = app(RecurrenceExpander::class);
        $startsAt = CarbonImmutable::parse('2026-08-24 19:00:00', 'America/Chicago')->utc();

        $this->assertSame('FREQ=WEEKLY;BYDAY=MO', $expander->canonicalize('FREQ=WEEKLY;BYDAY=MO', $startsAt, 'America/Chicago'));
        $this->assertNotEmpty($expander->describe(
            "DTSTART:20260824T190000\nRRULE:FREQ=WEEKLY;BYDAY=MO",
            $startsAt,
            'America/Chicago',
        ));
    }

    public function test_guest_reservation_consumes_capacity_and_stores_only_a_token_hash(): void
    {
        $lodge = Lodge::factory()->create(['timezone' => 'America/Chicago']);
        $event = Event::create([
            'lodge_id' => $lodge->id, 'slug' => 'open-house', 'status' => EventStatus::Published, 'title' => 'Open House',
            'time_zone' => 'America/Chicago', 'first_starts_at' => now(), 'duration_minutes' => 60, 'visibility' => EventVisibility::Public,
            'reservations_enabled' => true, 'guest_reservations_enabled' => true, 'capacity' => 2, 'maximum_party_size' => 2,
        ]);
        $occurrence = EventOccurrence::create([
            'event_id' => $event->id, 'lodge_id' => $lodge->id, 'recurrence_key' => '20260822T190000', 'original_starts_at' => now(),
            'starts_at' => now(), 'ends_at' => now()->addHour(), 'status' => EventOccurrenceStatus::Scheduled,
        ]);

        $result = app(EventReservationService::class)->reserve($occurrence, null, ['name' => 'Guest', 'email' => 'Guest@Example.test', 'party_size' => 2]);

        $this->assertSame(EventReservationStatus::Confirmed, $result->reservation->status);
        $this->assertSame(hash('sha256', $result->cancellationToken), $result->reservation->cancellation_token_hash);
    }

    public function test_guest_reminder_subscription_is_independent_from_reservations(): void
    {
        $lodge = Lodge::factory()->create(['timezone' => 'America/Chicago']);
        $event = Event::create([
            'lodge_id' => $lodge->id, 'slug' => 'lecture', 'status' => EventStatus::Published, 'title' => 'Lecture',
            'time_zone' => 'America/Chicago', 'first_starts_at' => now()->addWeek(), 'duration_minutes' => 60, 'visibility' => EventVisibility::Public,
            'reminders_enabled' => true, 'guest_reminders_enabled' => true,
        ]);
        $occurrence = EventOccurrence::create([
            'event_id' => $event->id, 'lodge_id' => $lodge->id, 'recurrence_key' => '20260828T190000', 'original_starts_at' => now()->addWeek(),
            'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHour(), 'status' => EventOccurrenceStatus::Scheduled,
        ]);

        $result = app(EventReminderSubscriptionService::class)->subscribe($event, $occurrence, null, ['name' => 'Guest', 'email' => 'Guest@Example.test']);

        $this->assertSame('active', $result->subscription->status->value);
        $this->assertSame(hash('sha256', $result->unsubscribeToken), $result->subscription->unsubscribe_token_hash);
        $this->assertDatabaseCount('event_reservations', 0);
    }

    public function test_guest_can_request_a_reminder_from_the_public_event_detail_flow(): void
    {
        $lodge = Lodge::factory()->create(['timezone' => 'America/Chicago']);
        $event = Event::create([
            'lodge_id' => $lodge->id, 'slug' => 'guest-reminder', 'status' => EventStatus::Published, 'title' => 'Guest Reminder',
            'time_zone' => 'America/Chicago', 'first_starts_at' => now()->addWeek(), 'duration_minutes' => 60, 'visibility' => EventVisibility::Public,
            'reminders_enabled' => true, 'guest_reminders_enabled' => true,
        ]);
        $occurrence = EventOccurrence::create([
            'event_id' => $event->id, 'lodge_id' => $lodge->id, 'recurrence_key' => '20260828T190000', 'original_starts_at' => now()->addWeek(),
            'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHour(), 'status' => EventOccurrenceStatus::Scheduled,
        ]);

        $this->post("/l/{$lodge->slug}/events/{$event->id}/reminders", [
            'name' => 'Guest', 'email' => 'guest@example.test', 'scope' => 'occurrence', 'occurrence_id' => $occurrence->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('event_reminder_subscriptions', [
            'event_id' => $event->id, 'event_occurrence_id' => $occurrence->id, 'normalized_email' => 'guest@example.test', 'status' => 'active',
        ]);
    }

    public function test_recurring_event_reminders_support_independent_occurrence_and_series_scopes(): void
    {
        $lodge = Lodge::factory()->create(['timezone' => 'America/Chicago']);
        $event = Event::create([
            'lodge_id' => $lodge->id, 'slug' => 'recurring-reminder', 'status' => EventStatus::Published, 'title' => 'Recurring Reminder',
            'time_zone' => 'America/Chicago', 'first_starts_at' => now()->addWeek(), 'duration_minutes' => 60, 'rrule' => 'FREQ=WEEKLY;COUNT=2',
            'visibility' => EventVisibility::Public, 'reminders_enabled' => true, 'guest_reminders_enabled' => true,
        ]);
        $firstOccurrence = EventOccurrence::create([
            'event_id' => $event->id, 'lodge_id' => $lodge->id, 'recurrence_key' => '20260828T190000', 'original_starts_at' => now()->addWeek(),
            'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHour(), 'status' => EventOccurrenceStatus::Scheduled,
        ]);
        EventOccurrence::create([
            'event_id' => $event->id, 'lodge_id' => $lodge->id, 'recurrence_key' => '20260904T190000', 'original_starts_at' => now()->addWeeks(2),
            'starts_at' => now()->addWeeks(2), 'ends_at' => now()->addWeeks(2)->addHour(), 'status' => EventOccurrenceStatus::Scheduled,
        ]);

        $payload = ['name' => 'Guest', 'email' => 'guest@example.test'];
        $this->post("/l/{$lodge->slug}/events/{$event->id}/reminders", $payload + [
            'scope' => 'occurrence', 'occurrence_id' => $firstOccurrence->id,
        ])->assertRedirect();
        $this->post("/l/{$lodge->slug}/events/{$event->id}/reminders", $payload + ['scope' => 'series'])
            ->assertRedirect();

        $this->assertDatabaseHas('event_reminder_subscriptions', [
            'event_id' => $event->id, 'event_occurrence_id' => $firstOccurrence->id, 'normalized_email' => 'guest@example.test',
        ]);
        $this->assertDatabaseHas('event_reminder_subscriptions', [
            'event_id' => $event->id, 'event_occurrence_id' => null, 'normalized_email' => 'guest@example.test',
        ]);
    }

    public function test_dispatcher_deduplicates_overlapping_recurring_reminder_scopes(): void
    {
        Queue::fake();
        $now = CarbonImmutable::now();
        $lodge = Lodge::factory()->create(['timezone' => 'America/Chicago']);
        $event = Event::create([
            'lodge_id' => $lodge->id, 'slug' => 'dispatch-reminder', 'status' => EventStatus::Published, 'title' => 'Dispatch Reminder',
            'time_zone' => 'America/Chicago', 'first_starts_at' => $now->addMinutes(30), 'duration_minutes' => 60, 'rrule' => 'FREQ=WEEKLY',
            'visibility' => EventVisibility::Public, 'reminders_enabled' => true, 'guest_reminders_enabled' => true,
        ]);
        $rule = $event->reminderRules()->create(['lodge_id' => $lodge->id, 'offset_minutes' => 60]);
        $occurrence = EventOccurrence::create([
            'event_id' => $event->id, 'lodge_id' => $lodge->id, 'recurrence_key' => '20260828T190000', 'original_starts_at' => $now->addMinutes(30),
            'starts_at' => $now->addMinutes(30), 'ends_at' => $now->addMinutes(90), 'status' => EventOccurrenceStatus::Scheduled,
        ]);
        $occurrenceSubscription = EventReminderSubscription::create([
            'event_id' => $event->id, 'lodge_id' => $lodge->id, 'event_occurrence_id' => $occurrence->id, 'email' => 'guest@example.test',
            'normalized_email' => 'guest@example.test', 'status' => ReminderSubscriptionStatus::Active,
        ]);
        EventReminderSubscription::create([
            'event_id' => $event->id, 'lodge_id' => $lodge->id, 'email' => 'guest@example.test',
            'normalized_email' => 'guest@example.test', 'status' => ReminderSubscriptionStatus::Active,
        ]);

        $this->assertSame(1, app(EventReminderDispatcher::class)->dispatchDue($now));
        $delivery = EventReminderDelivery::query()->sole();
        $this->assertSame($occurrenceSubscription->id, $delivery->event_reminder_subscription_id);
        $this->assertSame($rule->id, $delivery->event_reminder_rule_id);
        $this->assertSame(ReminderDeliveryStatus::Claimed, $delivery->status);
        Queue::assertPushed(SendEventReminderDelivery::class, fn (SendEventReminderDelivery $job) => $job->deliveryId === $delivery->id);
    }

    public function test_claimed_delivery_is_skipped_when_subscription_is_no_longer_active(): void
    {
        Notification::fake();
        $now = CarbonImmutable::now();
        $lodge = Lodge::factory()->create(['timezone' => 'America/Chicago']);
        $event = Event::create([
            'lodge_id' => $lodge->id, 'slug' => 'skipped-reminder', 'status' => EventStatus::Published, 'title' => 'Skipped Reminder',
            'time_zone' => 'America/Chicago', 'first_starts_at' => $now->addDay(), 'duration_minutes' => 60, 'visibility' => EventVisibility::Public,
        ]);
        $rule = $event->reminderRules()->create(['lodge_id' => $lodge->id, 'offset_minutes' => 60]);
        $occurrence = EventOccurrence::create([
            'event_id' => $event->id, 'lodge_id' => $lodge->id, 'recurrence_key' => '20260829T190000', 'original_starts_at' => $now->addDay(),
            'starts_at' => $now->addDay(), 'ends_at' => $now->addDay()->addHour(), 'status' => EventOccurrenceStatus::Scheduled,
        ]);
        $subscription = EventReminderSubscription::create([
            'event_id' => $event->id, 'lodge_id' => $lodge->id, 'event_occurrence_id' => $occurrence->id, 'email' => 'guest@example.test',
            'normalized_email' => 'guest@example.test', 'status' => ReminderSubscriptionStatus::Unsubscribed,
        ]);
        $delivery = EventReminderDelivery::create([
            'event_reminder_subscription_id' => $subscription->id, 'event_reminder_rule_id' => $rule->id, 'event_occurrence_id' => $occurrence->id,
            'event_id' => $event->id, 'lodge_id' => $lodge->id, 'normalized_email' => $subscription->normalized_email,
            'due_at' => $now, 'status' => ReminderDeliveryStatus::Claimed, 'claimed_at' => $now,
        ]);

        (new SendEventReminderDelivery($delivery->id))->handle();

        $this->assertSame(ReminderDeliveryStatus::Skipped, $delivery->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_platform_administrator_can_manage_event_category_catalog(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)->post('/platform/event-categories', ['name' => 'Breakfast', 'key' => 'breakfast'])
            ->assertRedirect();
        $category = EventCategory::query()->where('key', 'breakfast')->firstOrFail();
        $this->actingAs($admin)->put("/platform/event-categories/{$category->id}", [
            'name' => 'Lodge Breakfast', 'key' => 'breakfast', 'description' => 'Monthly fellowship meal', 'sort_order' => 25, 'is_active' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('event_categories', ['id' => $category->id, 'name' => 'Lodge Breakfast', 'is_active' => false, 'sort_order' => 25]);
    }

    public function test_authorized_user_can_persist_an_event_from_the_editor_payload(): void
    {
        $lodge = Lodge::factory()->create(['timezone' => 'America/Chicago']);
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)->post("/lodges/{$lodge->id}/events", [
            'title' => 'Open House', 'slug' => 'open-house', 'time_zone' => 'America/Chicago',
            'first_starts_at' => '2026-09-01T19:00', 'duration_minutes' => 120, 'visibility' => 'public',
            'reservations_enabled' => false, 'guest_reservations_enabled' => false, 'allows_cross_lodge_reservations' => false,
            'reminders_enabled' => true, 'guest_reminders_enabled' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('events', ['lodge_id' => $lodge->id, 'slug' => 'open-house', 'title' => 'Open House']);
        $event = Event::query()->where('lodge_id', $lodge->id)->where('slug', 'open-house')->firstOrFail();

        $this->actingAs($admin)->post("/lodges/{$lodge->id}/events/{$event->id}/publish")->assertRedirect();
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => EventStatus::Published->value]);
        $this->get("/l/{$lodge->slug}/events")->assertOk();
    }

    public function test_eligible_authenticated_members_can_see_non_public_upcoming_events(): void
    {
        $this->seed(PeopleMembershipReferenceSeeder::class);

        $lodge = Lodge::factory()->create(['timezone' => 'America/Chicago']);
        $person = Person::factory()->create();
        $member = User::factory()->create(['person_id' => $person->id]);
        Membership::create([
            'lodge_id' => $lodge->id,
            'person_id' => $person->id,
            'membership_status_id' => MembershipStatus::query()->where('key', 'active')->sole()->id,
            'masonic_degree_id' => MasonicDegree::query()->where('key', 'entered_apprentice')->sole()->id,
        ]);
        $event = Event::create([
            'lodge_id' => $lodge->id,
            'slug' => 'members-meeting',
            'status' => EventStatus::Published,
            'title' => 'Members Meeting',
            'time_zone' => 'America/Chicago',
            'first_starts_at' => now()->addWeek(),
            'duration_minutes' => 60,
            'visibility' => EventVisibility::Masons,
            'required_qualification' => 'ea',
        ]);
        $occurrence = EventOccurrence::create([
            'event_id' => $event->id,
            'lodge_id' => $lodge->id,
            'recurrence_key' => 'members-meeting-1',
            'original_starts_at' => now()->addWeek(),
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHour(),
            'status' => EventOccurrenceStatus::Scheduled,
        ]);

        $this->get("/l/{$lodge->slug}/events")->assertOk()->assertDontSee('Members Meeting');
        $this->get("/l/{$lodge->slug}/events/{$occurrence->id}")->assertNotFound();
        $this->actingAs($member)->get("/l/{$lodge->slug}/events")->assertOk()->assertSee('Members Meeting');
        $this->actingAs($member)->get("/l/{$lodge->slug}/events/{$occurrence->id}")->assertOk()->assertSee('Members Meeting');
    }

    public function test_public_events_page_materializes_recurring_instances_for_the_selected_horizon(): void
    {
        $lodge = Lodge::factory()->create(['timezone' => 'America/Chicago']);
        $event = Event::create([
            'lodge_id' => $lodge->id,
            'slug' => 'weekly-study',
            'status' => EventStatus::Published,
            'title' => 'Weekly Study',
            'time_zone' => 'America/Chicago',
            'first_starts_at' => now()->addDay(),
            'duration_minutes' => 60,
            'rrule' => 'FREQ=WEEKLY;COUNT=3',
            'visibility' => EventVisibility::Public,
        ]);

        $this->assertDatabaseMissing('event_occurrences', ['event_id' => $event->id]);
        $this->get("/l/{$lodge->slug}/events?range=60-days")->assertOk()->assertSee('Weekly Study');
        $this->assertDatabaseCount('event_occurrences', 3);
    }
}
