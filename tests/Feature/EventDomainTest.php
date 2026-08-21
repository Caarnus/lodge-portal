<?php

namespace Tests\Feature;

use App\Domain\Events\EventOccurrenceMaterializer;
use App\Domain\Events\EventReminderSubscriptionService;
use App\Domain\Events\EventReservationService;
use App\Enums\EventOccurrenceStatus;
use App\Enums\EventReservationStatus;
use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\EventOccurrence;
use App\Models\Lodge;
use App\Services\LodgeRoleCatalog;
use Carbon\CarbonImmutable;
use Database\Seeders\EventReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'time_zone' => 'America/Chicago', 'first_starts_at' => now(), 'duration_minutes' => 60, 'visibility' => EventVisibility::Public,
            'reminders_enabled' => true, 'guest_reminders_enabled' => true,
        ]);

        $result = app(EventReminderSubscriptionService::class)->subscribe($event, null, null, ['name' => 'Guest', 'email' => 'Guest@Example.test']);

        $this->assertSame('active', $result->subscription->status->value);
        $this->assertSame(hash('sha256', $result->unsubscribeToken), $result->subscription->unsubscribe_token_hash);
        $this->assertDatabaseCount('event_reservations', 0);
    }
}
