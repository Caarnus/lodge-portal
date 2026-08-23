<?php

namespace Database\Seeders;

use App\Enums\ContentVersionStatus;
use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Enums\GalleryVisibility;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\GalleryAlbum;
use App\Models\Lodge;
use App\Models\LodgeCommunication;
use App\Models\MasonicDegree;
use App\Models\Membership;
use App\Models\MembershipStatus;
use App\Models\Person;
use App\Models\User;
use App\Services\LodgeRoleCatalog;
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
        foreach (range(1, 12) as $number) {
            $this->member($a, 'Member', sprintf('%02d', $number), "member{$number}@washington.test");
        }
        foreach (range(1, 8) as $number) {
            $this->member($b, 'Franklin', sprintf('%02d', $number), "member{$number}@franklin.test");
        }
        $this->member($disabled, 'Disabled', 'Member', 'disabled@closed.test');
        $event = Event::create(['lodge_id' => $a->id, 'slug' => 'monthly-stated-meeting', 'title' => 'Monthly Stated Meeting', 'description' => '<p>Manual testing event.</p>', 'time_zone' => $a->timezone, 'first_starts_at' => now()->addDays(7)->setTime(19, 0), 'duration_minutes' => 120, 'visibility' => EventVisibility::Public, 'status' => EventStatus::Published, 'published_at' => now()]);
        EventOccurrence::create(['lodge_id' => $a->id, 'event_id' => $event->id, 'recurrence_key' => 'manual-meeting', 'original_starts_at' => now()->addDays(7), 'starts_at' => now()->addDays(7), 'ends_at' => now()->addDays(7)->addHours(2), 'status' => EventOccurrenceStatus::Scheduled]);
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

        return $user;
    }

    private function assign(Lodge $lodge, User $user, string $role): void
    {
        $lodge->roles()->where('name', $role)->sole()->users()->syncWithoutDetaching([$user->id => ['lodge_id' => $lodge->id]]);
    }
}
