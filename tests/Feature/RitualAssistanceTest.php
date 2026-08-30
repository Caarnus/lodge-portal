<?php

namespace Tests\Feature;

use App\Models\Lodge;
use App\Models\LodgeGroup;
use App\Models\LodgeGroupType;
use App\Models\Membership;
use App\Models\MembershipStatus;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonRitualAvailability;
use App\Models\PersonRitualProficiency;
use App\Models\RitualCategory;
use App\Models\RitualPart;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PeopleMembershipReferenceSeeder;
use Database\Seeders\LodgeGroupTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RitualAssistanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeopleMembershipReferenceSeeder::class);
    }

    public function test_search_is_ritual_scope_independent_and_projects_only_allowed_fields(): void
    {
        [$lodge, $user] = $this->requester();
        $person = $this->visibleCandidate($lodge);
        $person->directoryPrivacySetting()->update(['show_email' => true, 'show_phone' => false, 'scope' => 'hidden']);
        $person->update(['email' => 'visible@example.test', 'phone' => '555-111-2222']);

        $response = $this->actingAs($user)->getJson("/lodges/{$lodge->id}/ritual-assistance?searched=1");

        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $person->id)
            ->assertJsonPath('data.0.email', 'visible@example.test')
            ->assertJsonPath('data.0.phone', null)
            ->assertJsonMissingPath('data.0.parts.0.notes')
            ->assertJsonMissingPath('data.0.parts.0.performed_for_credit');
    }

    public function test_index_does_not_search_until_explicitly_requested(): void
    {
        [$lodge, $user] = $this->requester();
        $this->visibleCandidate($lodge);

        $this->actingAs($user)->getJson("/lodges/{$lodge->id}/ritual-assistance")
            ->assertOk()
            ->assertContent('{}');
    }

    public function test_initial_inertia_visit_renders_the_assistance_page(): void
    {
        [$lodge, $user] = $this->requester();

        $this->actingAs($user)->get("/lodges/{$lodge->id}/ritual-assistance")
            ->assertOk()
            ->assertSee('data-page');
    }

    public function test_detail_returns_404_after_visibility_revocation_and_requester_without_local_role_gets_403(): void
    {
        [$lodge, $user] = $this->requester();
        $person = $this->visibleCandidate($lodge);

        $this->actingAs($user)->getJson("/lodges/{$lodge->id}/ritual-assistance/{$person->id}")->assertOk();
        $person->ritualSetting()->update(['visibility_scope' => 'hidden']);
        $this->actingAs($user)->getJson("/lodges/{$lodge->id}/ritual-assistance/{$person->id}")->assertNotFound();

        $foreign = Lodge::factory()->create();
        $this->actingAs($user)->getJson("/lodges/{$foreign->id}/ritual-assistance")->assertForbidden();
    }

    public function test_availability_requires_exact_daypart_pair_and_filters_before_counting(): void
    {
        [$lodge, $user] = $this->requester();
        $person = $this->visibleCandidate($lodge);
        PersonRitualAvailability::factory()->create(['person_id' => $person->id, 'day_of_week' => 2, 'daypart' => 'evening']);

        $this->actingAs($user)->getJson("/lodges/{$lodge->id}/ritual-assistance?searched=1&day_of_week=2&daypart=evening")->assertJsonPath('total', 1);
        $this->actingAs($user)->getJson("/lodges/{$lodge->id}/ritual-assistance?searched=1&day_of_week=2")->assertUnprocessable();
        $this->actingAs($user)->getJson("/lodges/{$lodge->id}/ritual-assistance?searched=1&daypart=evening")->assertUnprocessable();
    }

    public function test_group_filter_narrows_candidates_without_restoring_hidden_or_unwilling_people(): void
    {
        $this->seed(LodgeGroupTypeSeeder::class);
        [$requestLodge, $user] = $this->requester();
        $inside = Lodge::factory()->create();
        $outside = Lodge::factory()->create();
        $group = LodgeGroup::create([
            'lodge_group_type_id' => LodgeGroupType::query()->where('key', 'region')->sole()->id,
            'name' => 'Southwest Indiana', 'slug' => 'southwest-indiana', 'is_active' => true, 'has_public_landing_page' => false,
        ]);
        $group->lodges()->attach($inside->id);
        $matching = $this->visibleCandidate($inside);
        $matching->ritualSetting()->update(['visibility_scope' => 'participating_lodges']);
        $hidden = $this->visibleCandidate($inside);
        $hidden->ritualSetting()->update(['visibility_scope' => 'hidden']);
        $unwilling = $this->visibleCandidate($inside);
        $unwilling->ritualSetting()->update(['visibility_scope' => 'participating_lodges']);
        $unwilling->ritualProficiencies()->update(['willing_to_assist' => false]);
        $foreign = $this->visibleCandidate($outside);
        $foreign->ritualSetting()->update(['visibility_scope' => 'participating_lodges']);

        $this->actingAs($user)->getJson("/lodges/{$requestLodge->id}/ritual-assistance?searched=1&audience=participating_lodges&group=southwest-indiana")
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $matching->id);
        $this->assertNotSame($matching->id, $hidden->id);
        $this->assertNotSame($matching->id, $unwilling->id);
        $this->assertNotSame($matching->id, $foreign->id);
    }

    private function requester(): array
    {
        $lodge = Lodge::factory()->create();
        $person = Person::factory()->create();
        Membership::factory()->create(['person_id' => $person->id, 'lodge_id' => $lodge->id, 'membership_status_id' => MembershipStatus::where('key', 'active')->sole()->id]);
        $user = User::factory()->create(['person_id' => $person->id]);
        $permission = Permission::firstOrCreate(['key' => 'ritual.search'], ['name' => 'Search ritual assistance']);
        $role = Role::create(['lodge_id' => $lodge->id, 'name' => 'Ritual Search', 'is_system' => false]);
        $role->permissions()->attach($permission);
        DB::table('lodge_user_roles')->insert(['lodge_id' => $lodge->id, 'user_id' => $user->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$lodge, $user];
    }

    private function visibleCandidate(Lodge $lodge): Person
    {
        $person = Person::factory()->create();
        Membership::factory()->create(['person_id' => $person->id, 'lodge_id' => $lodge->id, 'membership_status_id' => MembershipStatus::where('key', 'active')->sole()->id]);
        $person->ritualSetting()->update(['visibility_scope' => 'own_lodge', 'public_availability_note' => 'Weeknights']);
        $category = RitualCategory::factory()->create();
        $part = RitualPart::factory()->create(['ritual_category_id' => $category->id]);
        PersonRitualProficiency::factory()->create(['person_id' => $person->id, 'ritual_part_id' => $part->id, 'status' => 'proficient', 'willing_to_assist' => true, 'notes' => 'Private']);

        return $person;
    }
}
