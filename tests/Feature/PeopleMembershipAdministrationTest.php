<?php

namespace Tests\Feature;

use App\Models\Lodge;
use App\Models\MasonicDegree;
use App\Models\Membership;
use App\Models\MembershipStatus;
use App\Models\OfficerAssignment;
use App\Models\OfficerPosition;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonRelationship;
use App\Models\RelationshipType;
use App\Models\Role;
use App\Models\User;
use App\Models\WebsitePage;
use App\Services\LodgeRoleCatalog;
use App\Services\PersonMergeService;
use Database\Seeders\PeopleMembershipReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PeopleMembershipAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeopleMembershipReferenceSeeder::class);
    }

    public function test_people_are_reachable_only_through_an_active_membership_or_relationship(): void
    {
        $lodge = Lodge::factory()->create();
        $other = Lodge::factory()->create();
        $admin = $this->userFor($lodge, ['people.view', 'people.manage', 'relationships.view', 'relationships.manage']);
        $member = $this->member($lodge, ['primary_lodge_number' => $lodge->number]);
        $relative = Person::factory()->create();
        $unrelated = $this->member($other)->person;
        PersonRelationship::create(['owning_lodge_id' => $lodge->id, 'person_one_id' => $member->person_id,
            'person_two_id' => $relative->id, 'relationship_type_id' => RelationshipType::where('key', 'spouse')->value('id')]);

        $this->actingAs($admin)->get("/lodges/{$lodge->id}/people")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('people', 1)->where('people.0.id', $member->person_id));
        $this->actingAs($admin)->get("/lodges/{$lodge->id}/people?scope=related")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('people.0.id', $relative->id)->where('people.0.can_manage', true));
    }

    public function test_people_list_filters_by_search_status_degree_account_and_record_type(): void
    {
        $lodge = Lodge::factory()->create();
        $admin = $this->userFor($lodge, ['people.view']);
        $member = $this->member($lodge, ['member_number' => 'FILTER-42',
            'masonic_degree_id' => MasonicDegree::where('key', 'master_mason')->value('id')]);
        $member->person->update(['mailing_city' => 'Evansville', 'mailing_state' => 'IN']);
        User::factory()->create(['person_id' => $member->person_id]);
        $relative = Person::factory()->create();
        PersonRelationship::create(['owning_lodge_id' => $lodge->id, 'person_one_id' => $member->person_id,
            'person_two_id' => $relative->id, 'relationship_type_id' => RelationshipType::where('key', 'spouse')->value('id')]);

        $this->actingAs($admin)->get("/lodges/{$lodge->id}/people?search=filter-42&degree={$member->masonic_degree_id}&account=linked&scope=members")
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('people', 1)
            ->where('people.0.id', $member->person_id)->where('people.0.phone', $member->person->phone)
            ->where('people.0.mailing_city', 'Evansville')->where('people.0.memberships.0.status.name', 'Active'));

        $this->actingAs($admin)->get("/lodges/{$lodge->id}/people?scope=related")
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('people', 1)->where('people.0.id', $relative->id));
    }

    public function test_petitioner_status_is_available_and_visible_in_the_people_workspace(): void
    {
        $lodge = Lodge::factory()->create();
        $admin = $this->userFor($lodge, ['people.view', 'people.manage']);
        $petitioner = Person::factory()->create();
        Membership::factory()->create([
            'lodge_id' => $lodge->id,
            'person_id' => $petitioner->id,
            'membership_status_id' => MembershipStatus::query()->where('key', 'petitioner')->sole()->id,
            'primary_lodge_number' => $lodge->number,
        ]);

        $this->actingAs($admin)->get("/lodges/{$lodge->id}/people?scope=members")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('membershipStatuses', 6)
                ->where('people.0.id', $petitioner->id)
                ->where('people.0.memberships.0.status.name', 'Petitioner')
                ->where('people.0.can_manage', true));
    }

    public function test_people_workspace_includes_deceased_and_inactive_members_when_status_is_not_filtered(): void
    {
        $lodge = Lodge::factory()->create();
        $admin = $this->userFor($lodge, ['people.view', 'people.manage']);
        $former = Person::factory()->create([
            'legal_first_name' => 'Former',
            'legal_last_name' => 'Member',
            'is_deceased' => true,
        ]);
        $formerStatus = MembershipStatus::query()->where('key', 'demitted')->sole();
        Membership::factory()->create([
            'lodge_id' => $lodge->id,
            'person_id' => $former->id,
            'membership_status_id' => $formerStatus->id,
            'primary_lodge_number' => $lodge->number,
        ]);

        $this->actingAs($admin)->get("/lodges/{$lodge->id}/people?search=former&status=all")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('people', 1)
                ->where('people.0.id', $former->id)
                ->where('people.0.is_deceased', true)
                ->where('people.0.can_manage', true));

        $this->actingAs($admin)->get("/lodges/{$lodge->id}/people?status={$formerStatus->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('people', 1)
                ->where('people.0.id', $former->id));
    }

    public function test_people_list_sorting_and_phone_formatting_support_domestic_and_international_numbers(): void
    {
        $lodge = Lodge::factory()->create();
        $admin = $this->userFor($lodge, ['people.view', 'people.manage']);
        $first = $this->member($lodge);
        $second = $this->member($lodge);
        $first->person->update(['phone' => '1111111111']);
        $second->person->update(['phone' => '9999999999']);

        $this->actingAs($admin)->get("/lodges/{$lodge->id}/people?sort=phone&direction=desc")
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('people.0.id', $second->person_id)
            ->where('filters.sort', 'phone')->where('filters.direction', 'desc'));

        $person = $first->person;
        $payload = [
            'legal_first_name' => $person->legal_first_name,
            'legal_last_name' => $person->legal_last_name,
            'phone' => '8125550100',
            'is_deceased' => false,
        ];
        $this->actingAs($admin)->put("/lodges/{$lodge->id}/people/{$person->id}", $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('(812)555-0100', $person->fresh()->phone);
        $this->actingAs($admin)->get("/lodges/{$lodge->id}/people?search=8125550100")
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('people', 1)->where('people.0.id', $person->id));

        $this->actingAs($admin)->put("/lodges/{$lodge->id}/people/{$person->id}", array_merge($payload, ['phone' => '+44 20 7946 0958']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('+44 20 7946 0958', $person->fresh()->phone);

        $this->actingAs($admin)->put("/lodges/{$lodge->id}/people/{$person->id}", array_merge($payload, ['is_deceased' => true, 'death_date' => null]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($person->fresh()->is_deceased);
        $this->assertNull($person->fresh()->death_date);

        $this->actingAs($admin)->get("/lodges/{$lodge->id}/people?sort=membership")->assertOk();
        $this->actingAs($admin)->get("/lodges/{$lodge->id}/people?sort=location")->assertOk();
    }

    public function test_membership_honors_and_directional_relationships_are_returned_on_the_people_list(): void
    {
        $lodge = Lodge::factory()->create(['number' => '42']);
        $member = $this->member($lodge, ['primary_lodge_number' => '42']);
        $admin = $this->userFor($lodge, ['people.view', 'memberships.manage', 'relationships.view', 'relationships.manage']);

        $this->actingAs($admin)->put("/lodges/{$lodge->id}/memberships/{$member->id}", [
            'membership_status_id' => $member->membership_status_id,
            'is_award_of_gold' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/lodges/{$lodge->id}/memberships/{$member->id}/past-master-terms", ['year' => 2012])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/lodges/{$lodge->id}/people/{$member->person_id}/relationships", [
            'relationship_subject' => 'related',
            'relationship_type_id' => RelationshipType::where('key', 'child')->value('id'),
            'related_person' => ['legal_first_name' => 'Taylor', 'legal_last_name' => 'Example'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $relative = Person::where('name', 'Taylor Example')->firstOrFail();
        $this->actingAs($admin)->get("/lodges/{$lodge->id}/people?scope=members")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('people.0.memberships.0.is_award_of_gold', true)
            ->where('people.0.past_master_terms.0.year', 2012));

        $this->actingAs($admin)->get("/lodges/{$lodge->id}/people?past_master=1&award_of_gold=1")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('people', 1)
                ->where('people.0.id', $member->person_id)
                ->where('filters.past_master', true)
                ->where('filters.award_of_gold', true));
        $this->actingAs($admin)->get("/lodges/{$lodge->id}/people?scope=related")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('people.0.id', $relative->id)
                ->where('people.0.relationship_summaries.0.relationship_name', 'Child')
                ->where('people.0.relationship_summaries.0.related_person.id', $member->person_id)
                ->where('people.0.relationship_summaries.0.related_is_lodge_member', true));
    }

    public function test_member_role_cannot_reach_administrative_people_or_relationship_routes_but_can_use_directory(): void
    {
        $lodge = Lodge::factory()->create();
        app(LodgeRoleCatalog::class)->ensureFor($lodge);
        $membership = $this->member($lodge);
        $user = User::factory()->create(['person_id' => $membership->person_id, 'current_lodge_id' => null]);
        $memberRole = Role::where('lodge_id', $lodge->id)->where('name', 'Member')->firstOrFail();
        DB::table('lodge_user_roles')->insert(['lodge_id' => $lodge->id, 'user_id' => $user->id, 'role_id' => $memberRole->id,
            'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->get("/lodges/{$lodge->id}/people")->assertForbidden();
        $this->actingAs($user)->post("/lodges/{$lodge->id}/people/{$membership->person_id}/relationships", [])->assertNotFound();
        $this->actingAs($user)->get("/lodges/{$lodge->id}/directory")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('auth.lodges', 1)->where('auth.lodges.0.id', $lodge->id));
    }

    public function test_officer_and_administrator_roles_keep_people_access_while_platform_admin_has_no_directory_bypass(): void
    {
        $lodge = Lodge::factory()->create();
        app(LodgeRoleCatalog::class)->ensureFor($lodge);

        foreach (['Officer', 'Administrator'] as $roleName) {
            $membership = $this->member($lodge);
            $user = User::factory()->create(['person_id' => $membership->person_id]);
            $role = Role::query()->where('lodge_id', $lodge->id)->where('name', $roleName)->sole();
            DB::table('lodge_user_roles')->insert(['lodge_id' => $lodge->id, 'user_id' => $user->id, 'role_id' => $role->id,
                'created_at' => now(), 'updated_at' => now()]);

            $this->actingAs($user)->get("/lodges/{$lodge->id}/people")->assertOk();
        }

        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($platformAdmin)->get("/lodges/{$lodge->id}/people")->assertOk();
        $this->actingAs($platformAdmin)->get("/lodges/{$lodge->id}/directory")->assertForbidden();
    }

    public function test_relationship_can_be_edited_by_each_qualifying_primary_lodge_but_not_an_unrelated_lodge(): void
    {
        $a = Lodge::factory()->create(['number' => '10']);
        $b = Lodge::factory()->create(['number' => '20']);
        $c = Lodge::factory()->create(['number' => '30']);
        $one = $this->member($a, ['primary_lodge_number' => '10']);
        $two = $this->member($b, ['primary_lodge_number' => '20']);
        $relationship = PersonRelationship::create(['owning_lodge_id' => $a->id, 'person_one_id' => $one->person_id,
            'person_two_id' => $two->person_id, 'relationship_type_id' => RelationshipType::where('key', 'spouse')->value('id')]);
        $adminA = $this->userFor($a, ['relationships.manage']);
        $adminB = $this->userFor($b, ['relationships.manage']);
        $adminC = $this->userFor($c, ['relationships.manage']);

        $this->actingAs($adminC)->delete("/lodges/{$c->id}/relationships/{$relationship->id}")->assertForbidden();
        $this->actingAs($adminB)->put("/lodges/{$b->id}/relationships/{$relationship->id}", [
            'relationship_type_id' => RelationshipType::where('key', 'guardian')->value('id'),
            'subject_person_id' => $two->person_id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('ward', $relationship->fresh()->type->key);
        $this->actingAs($adminB)->delete("/lodges/{$b->id}/relationships/{$relationship->id}")->assertRedirect();
        $this->assertDatabaseMissing('person_relationships', ['id' => $relationship->id]);

        $replacement = PersonRelationship::create(['owning_lodge_id' => $a->id, 'person_one_id' => $one->person_id,
            'person_two_id' => $two->person_id, 'relationship_type_id' => RelationshipType::where('key', 'spouse')->value('id')]);
        $this->actingAs($adminA)->delete("/lodges/{$a->id}/relationships/{$replacement->id}")->assertRedirect();
    }

    public function test_public_officer_projection_excludes_private_contact_and_person_data(): void
    {
        $lodge = Lodge::factory()->create(['slug' => 'officer-lodge']);
        $creator = User::factory()->create();
        $membership = $this->member($lodge);
        $membership->person->update(['email' => 'private@example.test', 'phone' => '555-0100', 'birth_date' => '1980-01-01']);
        OfficerAssignment::create(['lodge_id' => $lodge->id, 'membership_id' => $membership->id,
            'officer_position_id' => OfficerPosition::where('key', 'worshipful_master')->value('id'),
            'is_public' => true, 'show_email' => false, 'show_phone' => true]);
        $page = WebsitePage::create(['lodge_id' => $lodge->id]);
        $version = $page->versions()->create(['lodge_id' => $lodge->id, 'status' => 'published', 'title' => 'Home', 'slug' => 'home',
            'is_home' => true, 'show_in_navigation' => true, 'navigation_order' => 0, 'created_by' => $creator->id]);
        $version->sections()->create(['lodge_id' => $lodge->id, 'type' => 'officers_placeholder', 'sort_order' => 0,
            'configuration' => ['heading' => 'Officers']]);

        $this->get('/l/officer-lodge')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('officers.0.position', 'Worshipful Master')->where('officers.0.email', null)
            ->where('officers.0.phone', '555-0100')->missing('officers.0.birth_date')->missing('officers.0.address'));
    }

    public function test_officer_assignment_is_lodge_bound_and_prompts_for_access_separately(): void
    {
        $lodge = Lodge::factory()->create();
        $other = Lodge::factory()->create();
        $membership = $this->member($lodge);
        $otherMembership = $this->member($other);
        $admin = $this->userFor($lodge, ['officers.manage']);
        $positionId = OfficerPosition::where('key', 'secretary')->value('id');
        $payload = ['membership_id' => $membership->id, 'officer_position_id' => $positionId,
            'is_public' => true, 'show_email' => false, 'show_phone' => false];

        $this->actingAs($admin)->post("/lodges/{$lodge->id}/officers", array_merge($payload, ['membership_id' => $otherMembership->id]))->assertNotFound();
        $this->actingAs($admin)->post("/lodges/{$lodge->id}/officers", $payload)->assertRedirect()
            ->assertSessionHas('officer_role_prompt', fn (array $prompt) => ! $prompt['has_linked_user'] && $prompt['action'] === 'assign');
        $this->assertDatabaseHas('officer_assignments', ['lodge_id' => $lodge->id, 'membership_id' => $membership->id,
            'officer_position_id' => $positionId, 'is_public' => true, 'show_email' => false]);
        $this->assertDatabaseCount('lodge_user_roles', 1);

        $replacement = $this->member($lodge);
        $this->actingAs($admin)->post("/lodges/{$lodge->id}/officers", array_merge($payload, ['membership_id' => $replacement->id]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('officer_assignments', 1);
        $this->assertDatabaseHas('officer_assignments', ['lodge_id' => $lodge->id, 'membership_id' => $replacement->id,
            'officer_position_id' => $positionId]);
    }

    public function test_profile_photo_original_and_derivative_remain_private_and_lodge_authorized(): void
    {
        Storage::fake('local');
        $lodge = Lodge::factory()->create();
        $other = Lodge::factory()->create();
        $membership = $this->member($lodge);
        $admin = $this->userFor($lodge, ['people.view', 'people.manage']);
        $otherAdmin = $this->userFor($other, ['people.view']);

        $this->actingAs($admin)->post("/lodges/{$lodge->id}/people/{$membership->person_id}/photo", [
            'photo' => UploadedFile::fake()->image('portrait.png', 400, 500),
        ])->assertRedirect();
        $person = $membership->person->fresh();
        $this->assertSame('ready', $person->profile_photo_status);
        Storage::disk('local')->assertExists($person->profile_photo_path);
        Storage::disk('local')->assertExists($person->profile_photo_derivative_path);
        $this->actingAs($admin)->get("/lodges/{$lodge->id}/people/{$person->id}/photo")->assertOk();
        $this->actingAs($otherAdmin)->get("/lodges/{$other->id}/people/{$person->id}/photo")->assertNotFound();
    }

    public function test_merge_moves_nonconflicting_history_and_retires_source(): void
    {
        $a = Lodge::factory()->create();
        $b = Lodge::factory()->create();
        $sourceMembership = $this->member($a);
        $survivorMembership = $this->member($b);
        $relative = Person::factory()->create();
        PersonRelationship::create(['owning_lodge_id' => $a->id, 'person_one_id' => $sourceMembership->person_id,
            'person_two_id' => $relative->id, 'relationship_type_id' => RelationshipType::where('key', 'child')->value('id')]);

        app(PersonMergeService::class)->merge($sourceMembership->person, $survivorMembership->person);

        $this->assertSoftDeleted('people', ['id' => $sourceMembership->person_id]);
        $this->assertDatabaseHas('memberships', ['id' => $sourceMembership->id, 'person_id' => $survivorMembership->person_id]);
        $this->assertDatabaseHas('person_relationships', ['person_one_id' => $survivorMembership->person_id, 'person_two_id' => $relative->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'person.merged']);
    }

    public function test_merge_conflicts_roll_back(): void
    {
        $lodge = Lodge::factory()->create();
        $source = $this->member($lodge);
        $survivor = $this->member($lodge);

        try {
            app(PersonMergeService::class)->merge($source->person, $survivor->person);
            $this->fail('Expected a merge conflict.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('memberships', ['id' => $source->id, 'person_id' => $source->person_id]);
            $this->assertNotSoftDeleted($source->person);
        }
    }

    public function test_new_nonmember_relative_can_be_created_without_a_membership(): void
    {
        $lodge = Lodge::factory()->create(['number' => '44']);
        $membership = $this->member($lodge, ['primary_lodge_number' => '44']);
        $admin = $this->userFor($lodge, ['people.view', 'relationships.manage']);

        $this->actingAs($admin)->post("/lodges/{$lodge->id}/people/{$membership->person_id}/relationships", [
            'relationship_type_id' => RelationshipType::where('key', 'spouse')->value('id'),
            'related_person' => ['legal_first_name' => 'Alex', 'legal_last_name' => 'Example', 'email' => 'alex@example.test'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $relative = Person::where('email', 'alex@example.test')->firstOrFail();
        $this->assertSame(0, $relative->memberships()->count());
        $this->assertDatabaseHas('person_relationships', ['person_one_id' => $membership->person_id, 'person_two_id' => $relative->id]);
    }

    public function test_account_invitation_links_matching_identity_without_automatically_granting_a_role(): void
    {
        Notification::fake();
        $lodge = Lodge::factory()->create();
        $membership = $this->member($lodge);
        $admin = $this->userFor($lodge, ['people.manage']);

        $this->actingAs($admin)->post("/lodges/{$lodge->id}/people/{$membership->person_id}/account")->assertRedirect();

        $account = User::where('person_id', $membership->person_id)->firstOrFail();
        $this->assertSame($membership->person->email, $account->email);
        $this->assertDatabaseMissing('lodge_user_roles', ['lodge_id' => $lodge->id, 'user_id' => $account->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'person.account_linked', 'lodge_id' => $lodge->id]);
    }

    public function test_custom_roles_cannot_escalate_beyond_the_actors_permissions(): void
    {
        $lodge = Lodge::factory()->create();
        $admin = $this->userFor($lodge, ['roles.manage']);
        $peopleManage = Permission::firstOrCreate(['key' => 'people.manage'], ['name' => 'Manage people']);
        $member = $this->member($lodge);
        $relative = Person::factory()->create();
        $relativeUser = User::factory()->create(['person_id' => $relative->id]);
        PersonRelationship::create(['owning_lodge_id' => $lodge->id, 'person_one_id' => $member->person_id,
            'person_two_id' => $relative->id, 'relationship_type_id' => RelationshipType::where('key', 'spouse')->value('id')]);

        $this->actingAs($admin)->get("/lodges/{$lodge->id}/role-assignments")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)->where('users.data.0.id', $relativeUser->id));
        $this->actingAs($admin)->post("/lodges/{$lodge->id}/roles", [
            'name' => 'Escalated role', 'permission_ids' => [$peopleManage->id],
        ])->assertSessionHasErrors('permission_ids');
        $this->assertDatabaseMissing('roles', ['lodge_id' => $lodge->id, 'name' => 'Escalated role']);
    }

    public function test_ending_membership_requires_only_an_end_date_and_preserves_person(): void
    {
        $lodge = Lodge::factory()->create();
        $membership = $this->member($lodge);
        $admin = $this->userFor($lodge, ['memberships.manage']);

        $this->actingAs($admin)->patch("/lodges/{$lodge->id}/memberships/{$membership->id}/end", ['end_date' => today()->toDateString()])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(today()->toDateString(), $membership->fresh()->end_date->toDateString());
        $this->assertDatabaseHas('people', ['id' => $membership->person_id, 'deleted_at' => null]);
    }

    private function member(Lodge $lodge, array $attributes = []): Membership
    {
        /** @var Membership $membership */
        $membership = Membership::factory()->createOne($attributes + ['lodge_id' => $lodge->id,
            'membership_status_id' => MembershipStatus::where('key', 'active')->value('id')]);

        return $membership;
    }

    private function userFor(Lodge $lodge, array $permissionKeys): User
    {
        $user = User::factory()->create();
        $role = Role::create(['lodge_id' => $lodge->id, 'name' => 'Test '.fake()->unique()->word()]);
        $permissions = collect($permissionKeys)->map(fn (string $key) => Permission::firstOrCreate(['key' => $key], ['name' => $key]));
        $role->permissions()->attach($permissions);
        DB::table('lodge_user_roles')->insert(['lodge_id' => $lodge->id, 'user_id' => $user->id, 'role_id' => $role->id,
            'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }
}
