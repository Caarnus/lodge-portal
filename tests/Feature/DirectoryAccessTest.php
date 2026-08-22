<?php

namespace Tests\Feature;

use App\Domain\Directory\DirectoryAccess;
use App\Enums\DirectoryAudience;
use App\Enums\DirectoryVisibilityScope;
use App\Models\Lodge;
use App\Models\MasonicDegree;
use App\Models\Membership;
use App\Models\MembershipStatus;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\LodgeRoleCatalog;
use Database\Seeders\PeopleMembershipReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DirectoryAccessTest extends TestCase
{
    use RefreshDatabase;

    private DirectoryAccess $access;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeopleMembershipReferenceSeeder::class);
        $this->access = app(DirectoryAccess::class);
    }

    public function test_requester_must_be_approved_verified_directory_authorized_active_member_of_active_lodge(): void
    {
        $lodge = Lodge::factory()->create();
        $user = $this->directoryUser($lodge);

        $this->assertTrue($this->access->canBrowse($user, $lodge));

        $user->forceFill(['email_verified_at' => null])->save();
        $this->assertFalse($this->access->canBrowse($user->fresh(), $lodge));

        $user->forceFill(['email_verified_at' => now(), 'approval_status' => 'pending'])->save();
        $this->assertFalse($this->access->canBrowse($user->fresh(), $lodge));

        $user->update(['approval_status' => 'approved']);
        $user->person->memberships()->where('lodge_id', $lodge->id)->update(['end_date' => today()]);
        $this->assertFalse($this->access->canBrowse($user->fresh(), $lodge));

        $platformOnly = User::factory()->create(['is_platform_admin' => true]);
        $this->assertFalse($this->access->canBrowse($platformOnly, $lodge));

        $personWithoutRole = $this->activePerson($lodge, DirectoryVisibilityScope::OwnLodge);
        $userWithoutRole = User::factory()->create(['person_id' => $personWithoutRole->id]);
        $this->assertFalse($this->access->canBrowse($userWithoutRole, $lodge));
    }

    public function test_visibility_applies_scope_active_membership_and_lodge_state(): void
    {
        $a = Lodge::factory()->create();
        $b = Lodge::factory()->create();
        $disabled = Lodge::factory()->create(['status' => 'disabled']);
        $requester = $this->directoryUser($a);
        $ownLodgeOnly = $this->activePerson($a, DirectoryVisibilityScope::OwnLodge);
        $crossLodge = $this->activePerson($b, DirectoryVisibilityScope::ParticipatingLodges);
        $hidden = $this->activePerson($a, DirectoryVisibilityScope::Hidden);
        $disabledMember = $this->activePerson($disabled, DirectoryVisibilityScope::ParticipatingLodges);
        $ended = $this->activePerson($a, DirectoryVisibilityScope::OwnLodge);
        $ended->memberships()->where('lodge_id', $a->id)->update(['end_date' => today()]);
        $deceased = $this->activePerson($a, DirectoryVisibilityScope::OwnLodge);
        $deceased->update(['is_deceased' => true]);
        $merged = $this->activePerson($a, DirectoryVisibilityScope::OwnLodge);
        $merged->update(['merged_at' => now()]);

        $this->assertTrue($this->access->canView($requester, $a, $ownLodgeOnly, DirectoryAudience::OwnLodge));
        $this->assertFalse($this->access->canView($requester, $a, $ownLodgeOnly, DirectoryAudience::ParticipatingLodges));
        $this->assertTrue($this->access->canView($requester, $a, $crossLodge, DirectoryAudience::ParticipatingLodges));

        foreach ([$hidden, $disabledMember, $ended, $deceased, $merged] as $person) {
            $this->assertFalse($this->access->canView($requester, $a, $person, DirectoryAudience::OwnLodge));
            $this->assertFalse($this->access->canView($requester, $a, $person, DirectoryAudience::ParticipatingLodges));
        }
    }

    public function test_hidden_email_and_phone_values_cannot_produce_directory_search_matches(): void
    {
        $lodge = Lodge::factory()->create();
        $hidden = $this->activePerson($lodge, DirectoryVisibilityScope::OwnLodge, [
            'email' => 'hidden-directory@example.test',
            'phone' => '812-555-0199',
        ]);
        $visible = $this->activePerson($lodge, DirectoryVisibilityScope::OwnLodge, [
            'email' => 'visible-directory@example.test',
            'phone' => '812-555-0101',
        ]);
        $visible->directoryPrivacySetting()->update(['show_email' => true, 'show_phone' => true]);

        $hiddenEmail = $this->access->search($lodge, DirectoryAudience::OwnLodge, 'hidden-directory');
        $hiddenPhone = $this->access->search($lodge, DirectoryAudience::OwnLodge, '0199');
        $visibleEmail = $this->access->search($lodge, DirectoryAudience::OwnLodge, 'visible-directory');
        $visiblePhone = $this->access->search($lodge, DirectoryAudience::OwnLodge, '0101');

        $this->assertSame(0, $hiddenEmail->total());
        $this->assertSame(0, $hiddenPhone->total());
        $this->assertSame([$visible->id], collect($visibleEmail->items())->pluck('id')->all());
        $this->assertSame([$visible->id], collect($visiblePhone->items())->pluck('id')->all());
        $this->assertNotSame($hidden->id, $visible->id);
    }

    public function test_missing_privacy_row_uses_conservative_own_lodge_defaults(): void
    {
        $lodge = Lodge::factory()->create();
        $person = $this->activePerson($lodge, DirectoryVisibilityScope::OwnLodge, [
            'email' => 'legacy@example.test',
            'phone' => '812-555-0100',
        ]);
        $person->directoryPrivacySetting()->delete();

        $own = $this->access->findVisible($lodge, $person, DirectoryAudience::OwnLodge);
        $cross = $this->access->findVisible($lodge, $person, DirectoryAudience::ParticipatingLodges);

        $this->assertNotNull($own);
        $this->assertNull($cross);
        $this->assertNull($this->access->project($own, $lodge, DirectoryAudience::OwnLodge)['email']);
        $this->assertNull($this->access->project($own, $lodge, DirectoryAudience::OwnLodge)['phone']);
    }

    public function test_projection_uses_requesting_lodge_degree_or_highest_cross_lodge_degree_and_optional_fields(): void
    {
        $a = Lodge::factory()->create();
        $b = Lodge::factory()->create();
        $person = $this->activePerson($a, DirectoryVisibilityScope::ParticipatingLodges, [
            'email' => 'member@example.test',
            'phone' => '812-555-0198',
            'mailing_address_line_1' => '101 Main Street',
            'mailing_city' => 'Evansville',
            'mailing_state' => 'IN',
            'mailing_postal_code' => '47708',
        ], 'entered_apprentice');
        $this->activeMembership($person, $b, 'master_mason');
        $person->directoryPrivacySetting()->update([
            'show_email' => true,
            'show_phone' => true,
            'show_address' => true,
            'show_degree' => true,
        ]);

        $own = $this->access->project(
            $this->access->findVisible($a, $person, DirectoryAudience::OwnLodge),
            $a,
            DirectoryAudience::OwnLodge,
        );
        $cross = $this->access->project(
            $this->access->findVisible($a, $person, DirectoryAudience::ParticipatingLodges),
            $a,
            DirectoryAudience::ParticipatingLodges,
        );

        $this->assertSame('Entered Apprentice', $own['degree']);
        $this->assertSame('Master Mason', $cross['degree']);
        $this->assertSame('member@example.test', $cross['email']);
        $this->assertSame('101 Main Street', $cross['address']['line_1']);
        $this->assertNull($cross['profile_photo_url']);
        $this->assertArrayNotHasKey('memberships', $cross);
        $this->assertArrayNotHasKey('directory_privacy_setting', $cross);
    }

    public function test_degree_filter_uses_own_lodge_degree_or_highest_cross_lodge_degree(): void
    {
        $a = Lodge::factory()->create();
        $b = Lodge::factory()->create();
        $dual = $this->activePerson($a, DirectoryVisibilityScope::ParticipatingLodges, [], 'entered_apprentice');
        $this->activeMembership($dual, $b, 'master_mason');
        $ea = $this->activePerson($b, DirectoryVisibilityScope::ParticipatingLodges, [], 'entered_apprentice');
        $dual->directoryPrivacySetting()->update(['show_degree' => true]);
        $ea->directoryPrivacySetting()->update(['show_degree' => true]);
        $eaId = MasonicDegree::query()->where('key', 'entered_apprentice')->sole()->id;

        $own = $this->access->search($a, DirectoryAudience::OwnLodge, degreeId: $eaId);
        $cross = $this->access->search($a, DirectoryAudience::ParticipatingLodges, degreeId: $eaId);

        $this->assertContains($dual->id, collect($own->items())->pluck('id')->all());
        $this->assertContains($ea->id, collect($cross->items())->pluck('id')->all());
        $this->assertNotContains($dual->id, collect($cross->items())->pluck('id')->all());
    }

    public function test_search_is_paginated_to_a_maximum_of_twenty_five_items(): void
    {
        $lodge = Lodge::factory()->create();
        foreach (range(1, 30) as $index) {
            $this->activePerson($lodge, DirectoryVisibilityScope::OwnLodge, [
                'legal_first_name' => 'Directory',
                'legal_last_name' => sprintf('Member %02d', $index),
                'name' => sprintf('Directory Member %02d', $index),
            ]);
        }

        $page = $this->access->search($lodge, DirectoryAudience::OwnLodge, 'Directory', perPage: 100);

        $this->assertSame(30, $page->total());
        $this->assertCount(25, $page->items());
        $this->assertSame(25, $page->perPage());
    }

    private function directoryUser(Lodge $lodge): User
    {
        app(LodgeRoleCatalog::class)->ensureFor($lodge);
        $person = $this->activePerson($lodge, DirectoryVisibilityScope::OwnLodge);
        $user = User::factory()->create(['person_id' => $person->id]);
        $memberRole = Role::query()->where('lodge_id', $lodge->id)->where('name', 'Member')->sole();
        DB::table('lodge_user_roles')->insert([
            'lodge_id' => $lodge->id,
            'user_id' => $user->id,
            'role_id' => $memberRole->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    private function activePerson(Lodge $lodge, DirectoryVisibilityScope $scope, array $attributes = [], ?string $degreeKey = null): Person
    {
        $person = Person::factory()->create($attributes);
        $this->activeMembership($person, $lodge, $degreeKey);
        $person->directoryPrivacySetting()->update(['scope' => $scope]);

        return $person;
    }

    private function activeMembership(Person $person, Lodge $lodge, ?string $degreeKey = null): Membership
    {
        return Membership::factory()->create([
            'person_id' => $person->id,
            'lodge_id' => $lodge->id,
            'membership_status_id' => MembershipStatus::query()->where('key', 'active')->sole()->id,
            'masonic_degree_id' => $degreeKey
                ? MasonicDegree::query()->where('key', $degreeKey)->sole()->id
                : null,
        ]);
    }
}
