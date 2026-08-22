<?php

namespace Database\Seeders;

use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventVolunteerPosition;
use App\Models\Lodge;
use App\Models\MasonicDegree;
use App\Models\Membership;
use App\Models\MembershipStatus;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\LodgeRoleCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class VolunteerManualTestingSeeder extends Seeder
{
    public function run(): void
    {
        app(LodgeRoleCatalog::class)->seedPermissions();
        $this->call([PeopleMembershipReferenceSeeder::class, EventReferenceSeeder::class]);

        $lodgeA = $this->lodge('Lodge A', '101', 'lodge-a');
        $this->lodge('Lodge B', '202', 'lodge-b');
        $manager = $this->member($lodgeA, 'Manager', 'Member', 'manager@lodge-a.test');
        $member = $this->member($lodgeA, 'Volunteer', 'Member', 'volunteer@lodge-a.test');
        $role = Role::query()->where('lodge_id', $lodgeA->id)->where('name', 'Administrator')->sole();
        $role->users()->syncWithoutDetaching([$manager->id => ['lodge_id' => $lodgeA->id]]);

        $event = Event::query()->firstOrCreate(['lodge_id' => $lodgeA->id, 'slug' => 'volunteer-open-house'], ['status' => EventStatus::Published, 'title' => 'Volunteer Open House', 'description' => '<p>Manual staffing test event.</p>', 'time_zone' => $lodgeA->timezone, 'first_starts_at' => now()->addDays(7)->setTime(19, 0), 'duration_minutes' => 120, 'visibility' => EventVisibility::Public, 'published_at' => now(), 'reminders_enabled' => true]);
        $startsAt = now()->addDays(7)->setTime(19, 0);
        $occurrence = EventOccurrence::query()->firstOrCreate(['event_id' => $event->id, 'recurrence_key' => 'manual-volunteer-open-house'], ['lodge_id' => $lodgeA->id, 'original_starts_at' => $startsAt, 'starts_at' => $startsAt, 'ends_at' => $startsAt->copy()->addHours(2), 'status' => EventOccurrenceStatus::Scheduled]);
        EventVolunteerPosition::query()->firstOrCreate(['event_id' => $event->id, 'event_occurrence_id' => null, 'name' => 'Setup'], ['lodge_id' => $lodgeA->id, 'needed_count' => 2, 'sort_order' => 10, 'is_active' => true]);
        EventVolunteerPosition::query()->firstOrCreate(['event_id' => $event->id, 'event_occurrence_id' => null, 'name' => 'Cleanup'], ['lodge_id' => $lodgeA->id, 'needed_count' => 2, 'sort_order' => 20, 'is_active' => true]);
        EventVolunteerPosition::query()->firstOrCreate(['event_id' => $event->id, 'event_occurrence_id' => $occurrence->id, 'name' => 'Registration Table'], ['lodge_id' => $lodgeA->id, 'needed_count' => 1, 'sort_order' => 30, 'is_active' => true]);

        $this->command?->info('Manual volunteer test accounts: manager@lodge-a.test and volunteer@lodge-a.test (password: password)');
    }

    private function lodge(string $name, string $number, string $slug): Lodge
    {
        $lodge = Lodge::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'number' => $number, 'city' => 'Evansville', 'state' => 'IN', 'jurisdiction' => 'Indiana', 'physical_address' => '100 Test Street', 'timezone' => 'America/Chicago', 'public_email' => "{$slug}@example.test", 'status' => 'active', 'primary_color' => '#1E3A5F', 'secondary_color' => '#D4AF37']);
        app(LodgeRoleCatalog::class)->ensureFor($lodge);

        return $lodge;
    }

    private function member(Lodge $lodge, string $first, string $last, string $email): User
    {
        $person = Person::query()->firstOrCreate(['email' => $email], ['name' => "{$first} {$last}", 'legal_first_name' => $first, 'legal_last_name' => $last]);
        $user = User::query()->firstOrCreate(['email' => $email], ['name' => $person->display_name, 'password' => Hash::make('password'), 'email_verified_at' => now(), 'approval_status' => 'approved', 'approved_at' => now(), 'person_id' => $person->id]);
        $user->update(['person_id' => $person->id, 'email_verified_at' => $user->email_verified_at ?? now(), 'approval_status' => 'approved', 'approved_at' => $user->approved_at ?? now()]);
        Membership::query()->firstOrCreate(['lodge_id' => $lodge->id, 'person_id' => $person->id], ['membership_status_id' => MembershipStatus::query()->where('key', 'active')->sole()->id, 'masonic_degree_id' => MasonicDegree::query()->where('key', 'master_mason')->sole()->id, 'primary_lodge_number' => $lodge->number]);

        return $user;
    }
}
