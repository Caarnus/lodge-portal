<?php

namespace Database\Seeders;

use App\Enums\ContentVersionStatus;
use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Enums\GalleryVisibility;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventReservation;
use App\Models\EventVolunteerCommitment;
use App\Models\EventVolunteerPosition;
use App\Models\GalleryAlbum;
use App\Models\Lodge;
use App\Models\LodgeCommunication;
use App\Models\MasonicDegree;
use App\Models\Membership;
use App\Models\MembershipStatus;
use App\Models\OfficerAssignment;
use App\Models\OfficerPosition;
use App\Models\PastMasterTerm;
use App\Models\Person;
use App\Models\User;
use App\Services\DefaultWebsiteTemplate;
use App\Services\LodgeRoleCatalog;
use App\Services\WebsitePublisher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ManualTestingSeeder extends Seeder
{
    public function run(): void
    {
        app(LodgeRoleCatalog::class)->seedPermissions();
        $this->call([PeopleMembershipReferenceSeeder::class, EventReferenceSeeder::class]);
        $a = $this->lodge('Washington Lodge', '101', 'washington-101');
        $b = $this->lodge('Franklin Lodge', '202', 'franklin-202');
        $disabled = $this->lodge('Closed Test Lodge', '303', 'closed-303', 'disabled');
        $admin = $this->member($a, 'Lodge', 'Administrator', 'admin@washington.test', true);
        $officer = $this->member($a, 'Oliver', 'Officer', 'officer@washington.test');
        $this->assign($a, $admin, 'Administrator');
        $this->assign($a, $officer, 'Officer');
        $this->website($a, $admin);
        $this->website($b, $this->member($b, 'Website', 'Publisher', 'publisher@franklin.test'));
        foreach (range(1, 12) as $number) {
            $this->member($a, 'Member', sprintf('%02d', $number), "member{$number}@washington.test");
        }
        foreach (range(1, 8) as $number) {
            $this->member($b, 'Franklin', sprintf('%02d', $number), "member{$number}@franklin.test");
        }
        $this->member($disabled, 'Disabled', 'Member', 'disabled@closed.test');
        $event = Event::create(['lodge_id' => $a->id, 'slug' => 'monthly-stated-meeting', 'title' => 'Monthly Stated Meeting', 'description' => '<p>Manual testing event.</p>', 'time_zone' => $a->timezone, 'first_starts_at' => now()->addDays(7)->setTime(19, 0), 'duration_minutes' => 120, 'visibility' => EventVisibility::Public, 'status' => EventStatus::Published, 'published_at' => now()]);
        $occurrence = EventOccurrence::create(['lodge_id' => $a->id, 'event_id' => $event->id, 'recurrence_key' => 'manual-meeting', 'original_starts_at' => now()->addDays(7), 'starts_at' => now()->addDays(7), 'ends_at' => now()->addDays(7)->addHours(2), 'status' => EventOccurrenceStatus::Scheduled]);
        $this->event($a, 'masonic-education', 'Masonic Education Night', EventVisibility::Masons, false);
        $this->event($a, 'lodge-business', 'Lodge Business Workshop', EventVisibility::Lodge, false);
        $reservationEvent = $this->event($a, 'community-breakfast', 'Community Breakfast', EventVisibility::Public, true);
        $reservationOccurrence = $reservationEvent->occurrences()->sole();
        $members = User::query()->where('email', 'like', 'member%@washington.test')->take(3)->get();
        foreach ($members as $index => $member) {
            EventReservation::create(['event_occurrence_id' => $reservationOccurrence->id, 'event_id' => $reservationEvent->id, 'lodge_id' => $a->id, 'user_id' => $member->id, 'person_id' => $member->person_id, 'name' => $member->name, 'email' => $member->email, 'normalized_email' => $member->email, 'party_size' => $index + 1, 'status' => 'confirmed', 'cancellation_token_hash' => hash('sha256', "manual-{$member->id}")]);
        }
        $position = EventVolunteerPosition::create(['lodge_id' => $a->id, 'event_id' => $event->id, 'event_occurrence_id' => $occurrence->id, 'name' => 'Registration table', 'needed_count' => 3, 'sort_order' => 10, 'is_active' => true]);
        EventVolunteerPosition::create(['lodge_id' => $a->id, 'event_id' => $event->id, 'event_occurrence_id' => $occurrence->id, 'name' => 'Setup crew', 'needed_count' => 2, 'sort_order' => 20, 'is_active' => true]);
        EventVolunteerCommitment::create(['lodge_id' => $a->id, 'event_id' => $event->id, 'event_occurrence_id' => $occurrence->id, 'event_volunteer_position_id' => $position->id, 'user_id' => $members[0]->id, 'person_id' => $members[0]->person_id, 'status' => 'committed', 'committed_at' => now(), 'created_by' => $admin->id]);
        $this->officers($a, $admin, $officer, $members);
        $issue = $a->newsletterIssues()->create(['slug' => 'spring-trestleboard', 'created_by' => $admin->id]);
        $issue->versions()->create(['lodge_id' => $a->id, 'status' => ContentVersionStatus::Published, 'title' => 'Spring Trestleboard', 'publication_date' => today(), 'body_html' => '<h2>Welcome brethren</h2><p>Manual newsletter content.</p>', 'created_by' => $admin->id, 'published_by' => $admin->id, 'published_at' => now()]);
        $album = GalleryAlbum::create(['lodge_id' => $a->id, 'slug' => 'public-open-house', 'created_by' => $admin->id]);
        $album->versions()->create(['lodge_id' => $a->id, 'status' => ContentVersionStatus::Published, 'title' => 'Open House', 'description' => 'Gallery ready for photo upload testing.', 'visibility' => GalleryVisibility::Public, 'created_by' => $admin->id, 'published_by' => $admin->id, 'published_at' => now()]);
        LodgeCommunication::create(['lodge_id' => $a->id, 'status' => 'draft', 'subject' => 'Manual test announcement', 'body_html' => '<p>Draft communication ready to send.</p>', 'created_by' => $officer->id, 'last_edited_by' => $officer->id]);
        $this->command?->info('Manual accounts: admin@washington.test, officer@washington.test, member1@washington.test — password: password');
    }

    private function lodge(string $name, string $number, string $slug, string $status = 'active'): Lodge
    {
        $lodge = Lodge::create(['name' => $name, 'number' => $number, 'slug' => $slug, 'city' => 'Evansville', 'state' => 'IN', 'jurisdiction' => 'Indiana', 'physical_address' => '100 Test Street', 'timezone' => 'America/Chicago', 'public_email' => "$slug@example.test", 'status' => $status, 'primary_color' => '#1E3A5F', 'secondary_color' => '#D4AF37']);
        app(LodgeRoleCatalog::class)->ensureFor($lodge);

        return $lodge;
    }

    private function member(Lodge $lodge, string $first, string $last, string $email, bool $platform = false): User
    {
        $person = Person::create(['name' => "$first $last", 'legal_first_name' => $first, 'legal_last_name' => $last, 'email' => $email, 'mailing_address_line_1' => '100 Test Street', 'mailing_city' => 'Evansville', 'mailing_state' => 'IN', 'mailing_postal_code' => '47708']);
        $user = User::create(['name' => $person->display_name, 'email' => $email, 'password' => Hash::make('password'), 'email_verified_at' => now(), 'approval_status' => 'approved', 'approved_at' => now(), 'person_id' => $person->id, 'is_platform_admin' => $platform]);
        Membership::create(['lodge_id' => $lodge->id, 'person_id' => $person->id, 'membership_status_id' => MembershipStatus::query()->where('key', 'active')->value('id'), 'masonic_degree_id' => MasonicDegree::query()->where('key', 'master_mason')->value('id'), 'primary_lodge_number' => $lodge->number]);
        $this->assign($lodge, $user, 'Member');

        return $user;
    }

    private function assign(Lodge $lodge, User $user, string $role): void
    {
        $lodge->roles()->where('name', $role)->sole()->users()->syncWithoutDetaching([$user->id => ['lodge_id' => $lodge->id]]);
    }

    private function website(Lodge $lodge, User $publisher): void
    {
        app(DefaultWebsiteTemplate::class)->apply($lodge, $publisher);
        $websitePublisher = app(WebsitePublisher::class);
        foreach ($lodge->websitePages()->orderBy('id')->get() as $page) {
            $websitePublisher->publish($page, $publisher);
        }
    }

    private function event(Lodge $lodge, string $slug, string $title, EventVisibility $visibility, bool $reservations): Event
    {
        $offset = match ($slug) {
            'masonic-education' => 14,
            'lodge-business' => 21,
            'community-breakfast' => 28,
            default => 10,
        };
        $starts = now()->addDays($offset)->setTime(18, 30);
        $isPublic = $visibility === EventVisibility::Public;
        $event = Event::create(['lodge_id' => $lodge->id, 'slug' => $slug, 'title' => $title, 'description' => "<p>{$title} manual test event.</p>", 'time_zone' => $lodge->timezone, 'first_starts_at' => $starts, 'duration_minutes' => 120, 'visibility' => $visibility, 'status' => EventStatus::Published, 'published_at' => now(), 'reservations_enabled' => $reservations, 'guest_reservations_enabled' => $reservations && $isPublic, 'reminders_enabled' => true, 'guest_reminders_enabled' => $isPublic, 'capacity' => $reservations ? 30 : null, 'maximum_party_size' => $reservations ? 6 : null]);
        $event->occurrences()->create(['lodge_id' => $lodge->id, 'recurrence_key' => "manual-{$slug}", 'original_starts_at' => $starts, 'starts_at' => $starts, 'ends_at' => $starts->copy()->addHours(2), 'status' => EventOccurrenceStatus::Scheduled]);

        return $event;
    }

    private function officers(Lodge $lodge, User $admin, User $officer, $members): void
    {
        $assignments = [['worshipful_master', $admin, true, true], ['secretary', $officer, true, false], ['treasurer', $members[1], false, false]];
        foreach ($assignments as [$key, $user, $public, $email]) {
            OfficerAssignment::create(['lodge_id' => $lodge->id, 'membership_id' => Membership::query()->where('lodge_id', $lodge->id)->where('person_id', $user->person_id)->sole()->id, 'officer_position_id' => OfficerPosition::query()->where('key', $key)->sole()->id, 'is_public' => $public, 'show_email' => $email, 'show_phone' => false]);
        }
        foreach ([2025 => $admin, 2024 => $officer, 2023 => $members[2]] as $year => $user) {
            PastMasterTerm::create(['lodge_id' => $lodge->id, 'person_id' => $user->person_id, 'year' => $year]);
        }
        $admin->person->directoryPrivacySetting()->update(['scope' => 'participating_lodges', 'show_email' => true, 'show_phone' => true, 'show_address' => false, 'show_degree' => true]);
        $officer->person->directoryPrivacySetting()->update(['scope' => 'own_lodge', 'show_email' => false, 'show_phone' => false, 'show_address' => false, 'show_degree' => true]);
        $members->each(fn (User $user, int $index) => $user->person->directoryPrivacySetting()->update(['scope' => $index % 2 ? 'hidden' : 'own_lodge', 'show_email' => $index % 3 === 0, 'show_phone' => false, 'show_address' => false, 'show_degree' => true]));
    }
}
