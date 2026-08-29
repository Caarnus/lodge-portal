<?php

namespace Tests\Feature;

use App\Models\Lodge;
use App\Models\Membership;
use App\Models\MembershipStatus;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonRitualProficiency;
use App\Models\RitualCategory;
use App\Models\RitualPart;
use App\Models\RitualProgramLevel;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PeopleMembershipReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LodgeRitualManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_officers_manage_permission_can_view_local_ritual_summaries_without_notes(): void
    {
        $this->seed(PeopleMembershipReferenceSeeder::class);
        $lodge = Lodge::factory()->create();
        $manager = $this->member($lodge);
        $candidate = $this->member($lodge);
        $part = RitualPart::factory()->create(['ritual_category_id' => RitualCategory::factory()->create()->id]);
        PersonRitualProficiency::factory()->create(['person_id' => $candidate->person_id, 'ritual_part_id' => $part->id, 'status' => 'proficient', 'willing_to_assist' => true, 'notes' => 'Private note']);
        $this->grant($manager, $lodge);

        $this->actingAs($manager)->get("/lodges/{$lodge->id}/ritual-management")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('ritual/MemberManagement')
                ->has('memberships', 2)
                ->where('memberships.1.proficiency_count', 1)
                ->missing('memberships.1.notes'));
    }

    public function test_member_without_officers_manage_permission_is_forbidden(): void
    {
        $this->seed(PeopleMembershipReferenceSeeder::class);
        $lodge = Lodge::factory()->create();
        $user = $this->member($lodge);
        $part = RitualPart::factory()->create(['ritual_category_id' => RitualCategory::factory()->create()->id]);

        $this->actingAs($user)->get("/lodges/{$lodge->id}/ritual-management")->assertForbidden();
        $this->actingAs($user)->put("/lodges/{$lodge->id}/ritual-management/{$this->membershipId($user, $lodge)}", $this->payload($part))->assertForbidden();
    }

    public function test_authorized_officer_updates_linked_local_member_without_exposing_or_changing_notes(): void
    {
        $this->seed(PeopleMembershipReferenceSeeder::class);
        $lodge = Lodge::factory()->create();
        $manager = $this->member($lodge);
        $candidate = $this->member($lodge);
        $this->grant($manager, $lodge);
        $part = RitualPart::factory()->create(['ritual_category_id' => RitualCategory::factory()->create()->id]);
        $record = PersonRitualProficiency::factory()->create(['person_id' => $candidate->person_id, 'ritual_part_id' => $part->id, 'notes' => 'Keep private']);

        $this->actingAs($manager)->get("/lodges/{$lodge->id}/ritual-management")
            ->assertInertia(fn (Assert $page) => $page->missing('memberships.1.proficiencies.0.notes'));
        $this->actingAs($manager)->put("/lodges/{$lodge->id}/ritual-management/{$this->membershipId($candidate, $lodge)}", $this->payload($part, ['notes' => 'Attempted edit']))
            ->assertSessionHasErrors('notes');
        $this->assertSame('Keep private', $record->fresh()->notes);

        $this->actingAs($manager)->put("/lodges/{$lodge->id}/ritual-management/{$this->membershipId($candidate, $lodge)}", $this->payload($part, ['parts' => [[
            'ritual_part_id' => $part->id, 'status' => 'proficient', 'interested_in_learning' => true, 'willing_to_assist' => true,
            'performed_for_credit' => false, 'confirm_performed_for_credit' => false, 'first_marked_proficient_on' => now()->toDateString(),
        ]]]))
            ->assertRedirect("/lodges/{$lodge->id}/ritual-management");
        $this->assertDatabaseHas('person_ritual_proficiencies', ['id' => $record->id, 'status' => 'proficient', 'interested_in_learning' => true, 'willing_to_assist' => true, 'notes' => 'Keep private']);
    }

    public function test_foreign_lodge_membership_cannot_be_updated(): void
    {
        $this->seed(PeopleMembershipReferenceSeeder::class);
        $lodge = Lodge::factory()->create();
        $foreign = Lodge::factory()->create();
        $manager = $this->member($lodge);
        $candidate = $this->member($foreign);
        $this->grant($manager, $lodge);
        $part = RitualPart::factory()->create(['ritual_category_id' => RitualCategory::factory()->create()->id]);

        $this->actingAs($manager)->put("/lodges/{$lodge->id}/ritual-management/{$this->membershipId($candidate, $foreign)}", $this->payload($part))->assertNotFound();
    }

    public function test_new_credit_requires_confirmation_and_reconciles_points_and_achievements(): void
    {
        $this->seed(PeopleMembershipReferenceSeeder::class);
        $lodge = Lodge::factory()->create();
        $manager = $this->member($lodge);
        $candidate = $this->member($lodge);
        $this->grant($manager, $lodge);
        $category = RitualCategory::factory()->create();
        $part = RitualPart::factory()->create(['ritual_category_id' => $category->id, 'counts_toward_program' => true, 'point_value' => 300]);
        $level = RitualProgramLevel::factory()->create(['point_threshold' => 300]);
        $url = "/lodges/{$lodge->id}/ritual-management/{$this->membershipId($candidate, $lodge)}";

        $this->actingAs($manager)->put($url, $this->payload($part, ['parts' => [[
            'ritual_part_id' => $part->id, 'status' => 'proficient', 'interested_in_learning' => false, 'willing_to_assist' => false,
            'performed_for_credit' => true, 'confirm_performed_for_credit' => false, 'first_marked_proficient_on' => null,
        ]]]))->assertSessionHasErrors('confirm_performed_for_credit');
        $this->actingAs($manager)->put($url, $this->payload($part, ['parts' => [[
            'ritual_part_id' => $part->id, 'status' => 'proficient', 'interested_in_learning' => false, 'willing_to_assist' => false,
            'performed_for_credit' => true, 'confirm_performed_for_credit' => true, 'first_marked_proficient_on' => null,
        ]]]))->assertRedirect();
        $this->assertDatabaseHas('person_ritual_proficiencies', ['person_id' => $candidate->person_id, 'ritual_part_id' => $part->id, 'performed_for_credit' => true]);
        $this->assertDatabaseHas('person_ritual_level_achievements', ['person_id' => $candidate->person_id, 'ritual_program_level_id' => $level->id]);
        $this->actingAs($manager)->get("/lodges/{$lodge->id}/ritual-management")
            ->assertInertia(fn (Assert $page) => $page->where('memberships.1.current_total', 300));
    }

    public function test_availability_is_replaced(): void
    {
        $this->seed(PeopleMembershipReferenceSeeder::class);
        $lodge = Lodge::factory()->create();
        $manager = $this->member($lodge);
        $candidate = $this->member($lodge);
        $this->grant($manager, $lodge);
        $part = RitualPart::factory()->create(['ritual_category_id' => RitualCategory::factory()->create()->id]);
        $candidate->person->ritualAvailabilities()->create(['day_of_week' => 1, 'daypart' => 'morning', 'is_enabled' => true]);

        $this->actingAs($manager)->put("/lodges/{$lodge->id}/ritual-management/{$this->membershipId($candidate, $lodge)}", $this->payload($part, ['windows' => [['day_of_week' => 7, 'daypart' => 'evening']]]))->assertRedirect();
        $this->assertDatabaseMissing('person_ritual_availabilities', ['person_id' => $candidate->person_id, 'day_of_week' => 1, 'daypart' => 'morning']);
        $this->assertDatabaseHas('person_ritual_availabilities', ['person_id' => $candidate->person_id, 'day_of_week' => 7, 'daypart' => 'evening', 'is_enabled' => true]);
    }

    private function member(Lodge $lodge): User
    {
        $person = Person::factory()->create();
        Membership::factory()->create(['lodge_id' => $lodge->id, 'person_id' => $person->id, 'membership_status_id' => MembershipStatus::where('key', 'active')->sole()->id]);

        return User::factory()->create(['person_id' => $person->id]);
    }

    private function grant(User $user, Lodge $lodge): void
    {
        $permission = Permission::firstOrCreate(['key' => 'officers.manage'], ['name' => 'Manage current lodge officers']);
        $role = Role::create(['lodge_id' => $lodge->id, 'name' => 'Ritual manager', 'is_system' => false]);
        $role->permissions()->attach($permission);
        DB::table('lodge_user_roles')->insert(['lodge_id' => $lodge->id, 'user_id' => $user->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function membershipId(User $user, Lodge $lodge): int
    {
        return Membership::where('person_id', $user->person_id)->where('lodge_id', $lodge->id)->sole()->id;
    }

    private function payload(RitualPart $part, array $overrides = []): array
    {
        return array_replace_recursive([
            'parts' => [[
                'ritual_part_id' => $part->id,
                'status' => 'not_known',
                'interested_in_learning' => false,
                'willing_to_assist' => false,
                'performed_for_credit' => false,
                'confirm_performed_for_credit' => false,
                'first_marked_proficient_on' => null,
            ]],
            'windows' => [['day_of_week' => 1, 'daypart' => 'morning']],
        ], $overrides);
    }
}
