<?php

namespace Tests\Feature;

use App\Enums\DirectoryVisibilityScope;
use App\Models\Lodge;
use App\Models\Membership;
use App\Models\MembershipStatus;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\LodgeRoleCatalog;
use Database\Seeders\PeopleMembershipReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DirectoryHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeopleMembershipReferenceSeeder::class);
    }

    public function test_active_directory_member_can_list_only_safe_own_lodge_projection(): void
    {
        $lodge = Lodge::factory()->create();
        $user = $this->directoryUser($lodge);
        $subject = $this->activePerson($lodge, DirectoryVisibilityScope::OwnLodge, [
            'email' => 'private@example.test',
            'phone' => '812-555-0199',
            'mailing_address_line_1' => 'Private Address',
        ]);

        $response = $this->actingAs($user)->get("/lodges/{$lodge->id}/directory?query={$subject->legal_last_name}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('directory/Index')
                ->where('people.total', 1)
                ->where('filters.audience', 'own_lodge'));

        $response->assertDontSee('private@example.test');
        $response->assertDontSee('812-555-0199');
        $response->assertDontSee('Private Address');
        $this->assertStringContainsString('private', $response->headers->get('cache-control'));
        $this->assertStringContainsString('no-store', $response->headers->get('cache-control'));
    }

    public function test_directory_requires_active_membership_and_directory_permission_for_requested_lodge(): void
    {
        $lodge = Lodge::factory()->create();
        $member = $this->activePerson($lodge, DirectoryVisibilityScope::OwnLodge);
        $user = User::factory()->create(['person_id' => $member->id]);

        $this->actingAs($user)->get("/lodges/{$lodge->id}/directory")->assertForbidden();
        $user = $this->directoryUser($lodge);
        $user->person->memberships()->where('lodge_id', $lodge->id)->update(['end_date' => now()]);
        $this->actingAs($user->fresh())->get("/lodges/{$lodge->id}/directory")->assertForbidden();
    }

    public function test_invisible_or_cross_lodge_subjects_return_not_found_after_requester_is_authorized(): void
    {
        $lodge = Lodge::factory()->create();
        $other = Lodge::factory()->create();
        $user = $this->directoryUser($lodge);
        $hidden = $this->activePerson($lodge, DirectoryVisibilityScope::Hidden);
        $otherOnly = $this->activePerson($other, DirectoryVisibilityScope::OwnLodge);

        $this->actingAs($user)->get("/lodges/{$lodge->id}/directory/{$hidden->id}")->assertNotFound();
        $this->actingAs($user)->get("/lodges/{$lodge->id}/directory/{$otherOnly->id}")->assertNotFound();
    }

    public function test_directory_photo_is_authorized_derivative_only_and_not_cached(): void
    {
        Storage::fake('local');
        $lodge = Lodge::factory()->create();
        $user = $this->directoryUser($lodge);
        $subject = $this->activePerson($lodge, DirectoryVisibilityScope::OwnLodge, [
            'profile_photo_path' => 'profile-originals/source.jpg',
            'profile_photo_derivative_path' => 'profile-photos/ready.jpg',
            'profile_photo_status' => 'ready',
        ]);
        $subject->directoryPrivacySetting()->update(['show_profile_photo' => true]);
        Storage::disk('local')->put('profile-originals/source.jpg', 'private original');
        Storage::disk('local')->put('profile-photos/ready.jpg', 'safe derivative');

        $response = $this->actingAs($user)->get("/lodges/{$lodge->id}/directory/{$subject->id}/photo")->assertOk();
        $this->assertStringContainsString('private', $response->headers->get('cache-control'));
        $this->assertStringContainsString('no-store', $response->headers->get('cache-control'));
        $response->assertDontSee('private original');

        $subject->directoryPrivacySetting()->update(['show_profile_photo' => false]);
        $this->actingAs($user)->get("/lodges/{$lodge->id}/directory/{$subject->id}/photo")->assertNotFound();
    }

    private function directoryUser(Lodge $lodge): User
    {
        app(LodgeRoleCatalog::class)->ensureFor($lodge);
        $person = $this->activePerson($lodge, DirectoryVisibilityScope::OwnLodge);
        $user = User::factory()->create(['person_id' => $person->id]);
        $memberRole = Role::query()->where('lodge_id', $lodge->id)->where('name', 'Member')->sole();
        DB::table('lodge_user_roles')->insert(['lodge_id' => $lodge->id, 'user_id' => $user->id, 'role_id' => $memberRole->id, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function activePerson(Lodge $lodge, DirectoryVisibilityScope $scope, array $attributes = []): Person
    {
        $person = Person::factory()->create($attributes);
        Membership::factory()->create([
            'person_id' => $person->id,
            'lodge_id' => $lodge->id,
            'membership_status_id' => MembershipStatus::query()->where('key', 'active')->sole()->id,
        ]);
        $person->directoryPrivacySetting()->update(['scope' => $scope]);

        return $person;
    }
}
