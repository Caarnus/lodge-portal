<?php

namespace Database\Seeders;

use App\Enums\ContentVersionStatus;
use App\Enums\EventOccurrenceStatus;
use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Enums\GalleryVisibility;
use App\Enums\WebsitePageStatus;
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
use App\Models\PersonRitualAvailability;
use App\Models\PersonRitualProficiency;
use App\Models\RitualPart;
use App\Models\User;
use App\Models\WebsitePage;
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
        $this->call([PeopleMembershipReferenceSeeder::class, EventReferenceSeeder::class, RitualReferenceSeeder::class]);
        $a = $this->lodge('Washington Lodge', '101', 'washington-101');
        $b = $this->lodge('Franklin Lodge', '202', 'franklin-202');
        $newburgh = $this->lodge('Newburgh Lodge No. 174 F. & A.M.', '174', 'newburgh-174', 'active', [
            'city' => 'Newburgh',
            'physical_address' => '720 Filmore Street',
            'public_email' => 'newburgh.lodge.174@gmail.com',
            'tag_line' => 'Brotherhood, service, and tradition since 1855.',
            'primary_color' => '#102A43',
            'secondary_color' => '#C9A227',
        ]);
        $disabled = $this->lodge('Closed Test Lodge', '303', 'closed-303', 'disabled');
        $admin = $this->member($a, 'Lodge', 'Administrator', 'admin@washington.test', true);
        $officer = $this->member($a, 'Oliver', 'Officer', 'officer@washington.test');
        $this->assign($a, $admin, 'Administrator');
        $this->assign($a, $officer, 'Officer');
        $this->website($a, $admin);
        $this->website($b, $this->member($b, 'Website', 'Publisher', 'publisher@franklin.test'));
        $newburghAdmin = $this->member($newburgh, 'Website', 'Administrator', 'admin@newburgh.test');
        $this->assign($newburgh, $newburghAdmin, 'Administrator');
        $newburghOfficers = [
            'worshipful_master' => $this->member($newburgh, 'David', 'Brickey II', 'david.brickey@example.test'),
            'senior_warden' => $this->member($newburgh, 'Tom', 'Metzger', 'tom.metzger@example.test'),
            'junior_warden' => $this->member($newburgh, 'Bryan', 'Bodkin', 'bryan.bodkin@example.test'),
            'treasurer' => $this->member($newburgh, 'Tom', 'Donnelly', 'tom.donnelly@example.test'),
            'secretary' => $this->member($newburgh, 'Chad', 'Hostetter', 'chad.hostetter@example.test'),
            'senior_deacon' => $this->member($newburgh, 'Mark', 'Lewellyn', 'mark.lewellyn@example.test'),
            'junior_deacon' => $this->member($newburgh, 'Casey', 'Garrison', 'casey.garrison@example.test'),
            'senior_steward' => $this->member($newburgh, 'Brandon', 'Bergner', 'brandon.bergner@example.test'),
            'junior_steward' => $this->member($newburgh, 'Jeffery', 'McCarroll', 'jeffery.mccarroll@example.test'),
            'chaplain' => $this->member($newburgh, 'Bob', 'Barnes', 'bob.barnes@example.test'),
            'trustee1' => $this->member($newburgh, 'Rod', 'McDonald', 'rod.mcdonald@example.test'),
            'trustee2' => $this->member($newburgh, 'Tim', 'Putnam', 'tim.putnam@example.test'),
        ];
        foreach (range(1, 12) as $number) {
            $this->member($a, 'Member', sprintf('%02d', $number), "member{$number}@washington.test");
        }
        foreach (range(1, 8) as $number) {
            $this->member($b, 'Franklin', sprintf('%02d', $number), "member{$number}@franklin.test");
        }
        $this->member($disabled, 'Disabled', 'Member', 'disabled@closed.test');
        $this->newburghTestSite($newburgh, $newburghAdmin, $newburghOfficers);
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
        $this->ritualFixtures($a, $b);
        $this->ritualAssistanceFixtures([$a, $b, $newburgh]);
        $this->command?->info('Manual accounts: admin@washington.test, admin@newburgh.test, officer@washington.test, member1@washington.test — password: password');
    }

    private function lodge(string $name, string $number, string $slug, string $status = 'active', array $attributes = []): Lodge
    {
        $lodge = Lodge::create(array_merge(['name' => $name, 'number' => $number, 'slug' => $slug, 'city' => 'Evansville', 'state' => 'IN', 'jurisdiction' => 'Indiana', 'physical_address' => '100 Test Street', 'timezone' => 'America/Chicago', 'public_email' => "$slug@example.test", 'status' => $status, 'primary_color' => '#1E3A5F', 'secondary_color' => '#D4AF37'], $attributes));
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

    private function newburghTestSite(Lodge $lodge, User $publisher, array $officers): void
    {
        $this->newburghOfficers($lodge, $officers);
        $this->newburghPastMasters($lodge, $officers);
        $event = $this->event($lodge, 'stated-meeting', 'Stated Meeting and Fellowship Dinner', EventVisibility::Public, false);
        $event->update([
            'description' => '<p>Join Newburgh Lodge for fellowship dinner at 6:00 p.m. followed by our stated meeting at 7:00 p.m.</p>',
            'location_name' => 'Newburgh Masonic Lodge',
            'location_details' => '720 Filmore Street, Newburgh, Indiana',
        ]);
        $issue = $lodge->newsletterIssues()->create(['slug' => 'autumn-trestleboard', 'created_by' => $publisher->id]);
        $issue->versions()->create(['lodge_id' => $lodge->id, 'status' => ContentVersionStatus::Published, 'title' => 'Newburgh Trestleboard — Autumn Edition', 'publication_date' => today()->subMonth(), 'body_html' => '<h2>From the East</h2><p>Our test trestleboard includes meeting reminders, service opportunities, and news for the brethren.</p>', 'created_by' => $publisher->id, 'published_by' => $publisher->id, 'published_at' => now()]);
        $album = GalleryAlbum::create(['lodge_id' => $lodge->id, 'slug' => 'lodge-life', 'created_by' => $publisher->id]);
        $album->versions()->create(['lodge_id' => $lodge->id, 'status' => ContentVersionStatus::Published, 'title' => 'Life at Newburgh Lodge', 'description' => 'A test gallery for fellowship, service, and lodge events.', 'visibility' => GalleryVisibility::Public, 'created_by' => $publisher->id, 'published_by' => $publisher->id, 'published_at' => now()]);

        $pages = [
            ['title' => 'Home', 'slug' => 'home', 'is_home' => true, 'order' => 0, 'visibility' => 'public', 'sections' => [
                ['type' => 'hero', 'configuration' => ['heading' => 'WELCOME TO NEWBURGH LODGE No. 174 F. & A.M.', 'body' => 'Stated meetings are held on the third Tuesday of every month. Dinner begins at 6:00 p.m.; lodge opens at 7:00 p.m.', 'media_id' => null]],
                ['type' => 'rich_text', 'configuration' => ['html' => '<h2>Building Character. Serving Our Community.</h2><p>Freemasonry builds and advances the character of men while placing brotherly love and moral integrity at the center of our work. Newburgh Lodge welcomes members, visiting brethren, and men who would like to learn more.</p><h2>At a Glance</h2><ul><li><strong>Stated meetings:</strong> third Tuesday of every month</li><li><strong>Degrees:</strong> fourth Tuesday</li><li><strong>Community breakfasts:</strong> first Saturday, March through November</li></ul>']],
                ['type' => 'call_to_action', 'configuration' => ['heading' => 'Visit Newburgh Lodge', 'body' => 'Find us at 720 Filmore Street in Newburgh. Please use P.O. Box 490, Newburgh, IN 47629-0490 for lodge mail.', 'label' => 'Contact / Visit the Lodge', 'url' => '/contact']],
                ['type' => 'events_placeholder', 'configuration' => ['heading' => 'Upcoming at the Lodge', 'body' => 'Join us for meetings, fellowship, and community service.', 'maximum_items' => 6, 'show_all_link' => true]],
            ]],
            ['title' => 'History', 'slug' => 'history', 'is_home' => false, 'order' => 10, 'visibility' => 'public', 'sections' => [
                ['type' => 'rich_text', 'configuration' => ['html' => '<h1>History of Newburgh Lodge</h1><p>Newburgh Lodge No. 174 received its charter from the Grand Lodge of Indiana on May 29, 1855. The brethren first met under dispensation in June 1854, and the lodge soon made its home on State Street in downtown Newburgh.</p><p>For more than a century, meetings were held in that historic building. In 1962, Indiana Grand Lodge officers laid the cornerstone for a new lodge building at 720 Filmore Street, where Newburgh Lodge continues to meet today. The lodge has endured periods of growth, war, economic hardship, and change while preserving a tradition of fellowship and service.</p>']],
            ]],
            ['title' => 'FAQ', 'slug' => 'faq', 'is_home' => false, 'order' => 15, 'visibility' => 'public', 'sections' => [
                ['type' => 'rich_text', 'configuration' => ['html' => '<h1>Frequently Asked Questions</h1><h2>What is Freemasonry?</h2><p>Freemasonry is a fraternity that encourages members to live with integrity, serve their communities, and support one another.</p><h2>How do I learn more or ask about joining?</h2><p>Contact Newburgh Lodge to arrange a visit or conversation. Membership begins with a candidate expressing his own interest.</p><h2>May I visit a meeting?</h2><p>Visiting brethren are welcome. Prospective members should contact the lodge before attending so we can help plan a visit.</p><h2>When does the lodge meet?</h2><p>Newburgh Lodge normally meets on the third Tuesday, with dinner at 6:00 p.m. and lodge at 7:00 p.m.</p>']],
            ]],
            ['title' => 'Events', 'slug' => 'events', 'is_home' => false, 'order' => 20, 'visibility' => 'public', 'sections' => [
                ['type' => 'events_placeholder', 'configuration' => ['heading' => 'Lodge Calendar', 'body' => 'Find upcoming meetings and community events.', 'maximum_items' => 12, 'show_all_link' => true]],
            ]],
            ['title' => 'Officers', 'slug' => 'officers', 'is_home' => false, 'order' => 30, 'visibility' => 'public', 'sections' => [
                ['type' => 'officers_placeholder', 'configuration' => ['heading' => 'Lodge Officers', 'body' => 'Meet the brethren serving Newburgh Lodge this year.']],
            ]],
            ['title' => 'Past Masters', 'slug' => 'past-masters', 'is_home' => false, 'order' => 35, 'visibility' => 'public', 'sections' => [
                ['type' => 'past_masters_placeholder', 'configuration' => ['heading' => 'Past Masters', 'body' => 'Newburgh Lodge honors the brethren who have served as Worshipful Master.']],
            ]],
            ['title' => 'Gallery', 'slug' => 'gallery', 'is_home' => false, 'order' => 40, 'visibility' => 'public', 'sections' => [
                ['type' => 'gallery_placeholder', 'configuration' => ['heading' => 'Lodge Life', 'body' => 'Photos from fellowship, service, and events.', 'gallery_album_ids' => [$album->id]]],
            ]],
            ['title' => 'Links', 'slug' => 'links', 'is_home' => false, 'order' => 45, 'visibility' => 'public', 'sections' => [
                ['type' => 'link_list', 'configuration' => ['heading' => 'Lodge & Masonic Resources', 'links' => [['label' => 'Newburgh Lodge on Facebook', 'url' => 'https://www.facebook.com/newburghlodge174/'], ['label' => 'Grand Lodge of Indiana', 'url' => 'https://www.indianafreemasons.com/'], ['label' => 'Indiana Freemasons Lodge Directory', 'url' => 'https://www.indianafreemasons.com/indianamap'], ['label' => 'Directions to Newburgh Lodge', 'url' => 'https://www.google.com/maps/search/?api=1&query=720+Filmore+St+Newburgh+IN+47630']]]],
            ]],
            ['title' => 'Newsletter', 'slug' => 'newsletter', 'is_home' => false, 'order' => 50, 'visibility' => 'lodge', 'sections' => [
                ['type' => 'newsletter_placeholder', 'configuration' => ['heading' => 'Member Trestleboard', 'body' => 'Read lodge news and upcoming reminders.']],
            ]],
            ['title' => 'Directory', 'slug' => 'directory', 'is_home' => false, 'order' => 55, 'visibility' => 'lodge', 'sections' => [
                ['type' => 'directory_placeholder', 'configuration' => ['heading' => 'Member Directory', 'body' => 'Search the Newburgh Lodge member directory.']],
            ]],
            ['title' => 'Contact', 'slug' => 'contact', 'is_home' => false, 'order' => 60, 'visibility' => 'public', 'sections' => [
                ['type' => 'contact_information', 'configuration' => ['heading' => 'Contact Newburgh Lodge', 'body' => '720 Filmore Street, Newburgh, IN 47630. Do not mail to the street address; use P.O. Box 490, Newburgh, IN 47629-0490. Email newburgh.lodge.174@gmail.com. We welcome questions from prospective members, visitors, and the community.', 'show_contact_form' => true]],
                ['type' => 'meeting_information', 'configuration' => ['heading' => 'Meeting Information', 'body' => 'Stated meetings are held on the third Tuesday. Degrees are normally held on the fourth Tuesday. Dinner begins at 6:00 p.m.; lodge opens at 7:00 p.m.']],
            ]],
        ];
        $websitePublisher = app(WebsitePublisher::class);
        foreach ($pages as $pageData) {
            $page = WebsitePage::create(['lodge_id' => $lodge->id]);
            $version = $page->versions()->create(['lodge_id' => $lodge->id, 'status' => WebsitePageStatus::Draft, 'title' => $pageData['title'], 'slug' => $pageData['slug'], 'is_home' => $pageData['is_home'], 'show_in_navigation' => true, 'navigation_visibility' => $pageData['visibility'], 'navigation_order' => $pageData['order'], 'created_by' => $publisher->id]);
            foreach ($pageData['sections'] as $order => $section) {
                $version->sections()->create(['lodge_id' => $lodge->id, 'type' => $section['type'], 'sort_order' => $order, 'configuration' => $section['configuration']]);
            }
            $websitePublisher->publish($page, $publisher);
        }
    }

    private function newburghOfficers(Lodge $lodge, array $officers): void
    {
        $officers['trustee3'] = $officers['treasurer'];
        foreach ($officers as $key => $user) {
            OfficerAssignment::create(['lodge_id' => $lodge->id, 'membership_id' => Membership::query()->where('lodge_id', $lodge->id)->where('person_id', $user->person_id)->sole()->id, 'officer_position_id' => OfficerPosition::query()->where('key', $key)->sole()->id, 'is_public' => true, 'show_email' => false, 'show_phone' => false]);
        }
    }

    private function newburghPastMasters(Lodge $lodge, array $officers): void
    {
        $terms = [2026 => $officers['worshipful_master'], 2024 => $officers['secretary'], 2023 => 'Brandon Goodall', 2022 => 'Ken Mitz', 2021 => 'Jason Warren', 2020 => 'Ken Mitz', 2019 => 'Ron Markham', 2018 => 'Paul Rainey', 2017 => 'Chad Steckler', 2016 => $officers['senior_deacon'], 2015 => 'David Hart', 2014 => 'Benjamin Larramore', 2013 => 'Kevin L. Cobb', 2012 => 'Ronald E. Millikan', 2011 => 'Martin R. Helm', 2010 => 'Jacob Heubner', 2009 => 'Charles Milligan', 2008 => 'Frank G. Bolin', 2007 => 'Randall E. Beem', 2006 => 'Garry Bradley', 2005 => 'Paul E. Rainey', 2004 => 'Robert E. Addington', 2003 => 'Dennis T. Bolin', 2002 => 'Terry G. Brown', 2001 => 'Daniel T. Brown', 2000 => 'Harold A. Bloss', 1999 => 'Loren T. Dixon', 1998 => 'Michael E. Cannon', 1997 => 'William C. Peppiatt', 1996 => 'Brian R. Burdette'];
        foreach ($terms as $year => $member) {
            if ($member instanceof User) {
                $person = $member->person;
            } else {
                $person = Person::create([
                    'name' => $member,
                    'legal_first_name' => (string) str($member)->before(' '),
                    'legal_last_name' => (string) str($member)->afterLast(' '),
                    'is_deceased' => $year <= 2004,
                ]);
            }
            PastMasterTerm::create(['lodge_id' => $lodge->id, 'person_id' => $person->id, 'year' => $year]);
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

    private function ritualFixtures(Lodge $a, Lodge $b): void
    {
        $ownLodge = User::query()->where('email', 'member1@washington.test')->sole();
        $participating = User::query()->where('email', 'member2@washington.test')->sole();
        Membership::create([
            'lodge_id' => $b->id,
            'person_id' => $participating->person_id,
            'membership_status_id' => MembershipStatus::query()->where('key', 'active')->value('id'),
            'masonic_degree_id' => MasonicDegree::query()->where('key', 'master_mason')->value('id'),
            'primary_lodge_number' => $a->number,
        ]);
        $part = RitualPart::query()->where('is_active', true)->orderBy('sort_order')->firstOrFail();
        foreach ([[$ownLodge, 'own_lodge', 2, 'evening'], [$participating, 'participating_lodges', 4, 'afternoon']] as [$user, $scope, $day, $daypart]) {
            $user->person->ritualSetting()->update(['visibility_scope' => $scope, 'public_availability_note' => 'Broad availability only; please contact separately.', 'updated_by' => $user->id]);
            PersonRitualProficiency::create(['person_id' => $user->person_id, 'ritual_part_id' => $part->id, 'status' => 'proficient', 'willing_to_assist' => true]);
            PersonRitualAvailability::create(['person_id' => $user->person_id, 'day_of_week' => $day, 'daypart' => $daypart, 'is_enabled' => true]);
        }
        $ownLodge->person->directoryPrivacySetting()->update(['show_email' => true, 'show_phone' => false]);
        $participating->person->directoryPrivacySetting()->update(['show_email' => true, 'show_phone' => true]);
    }

    private function ritualAssistanceFixtures(array $lodges): void
    {
        $parts = RitualPart::query()->where('is_active', true)->orderBy('ritual_category_id')->orderBy('sort_order')->get()->values();

        foreach (range(1, 54) as $number) {
            $lodge = $lodges[($number - 1) % count($lodges)];
            $user = $this->member($lodge, 'Ritual', sprintf('Tester %02d', $number), "ritual{$number}@manual.test");
            $primaryPart = $parts[($number - 1) % $parts->count()];
            $secondaryPart = $parts[$number % $parts->count()];

            $user->person->ritualSetting()->updateOrCreate([], [
                'visibility_scope' => $number % 4 === 0 ? 'own_lodge' : 'participating_lodges',
                'public_availability_note' => "Manual fixture {$number}: contact separately.",
                'updated_by' => $user->id,
            ]);
            $user->person->directoryPrivacySetting()->updateOrCreate([], [
                'scope' => 'hidden',
                'show_email' => $number % 3 === 0,
                'show_phone' => $number % 5 === 0,
                'show_address' => false,
                'show_degree' => true,
            ]);

            PersonRitualProficiency::query()->updateOrCreate(
                ['person_id' => $user->person_id, 'ritual_part_id' => $primaryPart->id],
                ['status' => 'proficient', 'willing_to_assist' => true, 'interested_in_learning' => $number % 2 === 0, 'performed_for_credit' => $number % 6 === 0],
            );

            $secondary = match ($number % 3) {
                0 => ['status' => 'learning', 'interested_in_learning' => true, 'willing_to_assist' => false],
                1 => ['status' => 'proficient', 'interested_in_learning' => false, 'willing_to_assist' => false],
                default => ['status' => 'proficient', 'interested_in_learning' => true, 'willing_to_assist' => true],
            };
            PersonRitualProficiency::query()->updateOrCreate(
                ['person_id' => $user->person_id, 'ritual_part_id' => $secondaryPart->id],
                $secondary,
            );

            foreach ([$number % 7 + 1 => $number % 2 === 0 ? 'evening' : 'afternoon', ($number + 2) % 7 + 1 => 'morning'] as $day => $daypart) {
                PersonRitualAvailability::query()->updateOrCreate(
                    ['person_id' => $user->person_id, 'day_of_week' => $day, 'daypart' => $daypart],
                    ['is_enabled' => true],
                );
            }
        }
    }
}
