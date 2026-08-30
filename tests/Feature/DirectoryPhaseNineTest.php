<?php

namespace Tests\Feature;

use App\Domain\Directory\DirectoryAccess;
use App\Enums\DirectoryAudience;
use App\Enums\DirectoryVisibilityScope;
use App\Models\Lodge;
use App\Models\LodgeGroup;
use App\Models\LodgeGroupType;
use App\Models\Membership;
use App\Models\MembershipStatus;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\LodgeRoleCatalog;
use Database\Seeders\LodgeGroupTypeSeeder;
use Database\Seeders\PeopleMembershipReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DirectoryPhaseNineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeopleMembershipReferenceSeeder::class);
        $this->seed(LodgeGroupTypeSeeder::class);
    }

    public function test_cross_lodge_blank_browse_projects_only_safe_active_affiliations(): void
    {
        $requestLodge = Lodge::factory()->create();
        $other = Lodge::factory()->create();
        $requester = $this->directoryUser($requestLodge);
        $subject = $this->person($requestLodge, DirectoryVisibilityScope::ParticipatingLodges);
        $this->membership($subject, $other);

        $this->actingAs($requester)->get("/lodges/{$requestLodge->id}/directory?audience=participating_lodges")
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('people.data', 1)
                ->has('people.data.0.affiliations', 2)
                ->where('people.data.0.affiliations.0.id', $requestLodge->id)
                ->missing('people.data.0.affiliations.0.member_number')
                ->missing('people.data.0.affiliations.0.status'));
    }

    public function test_group_filter_narrows_cross_lodge_subjects_without_duplicates(): void
    {
        $requestLodge = Lodge::factory()->create();
        $inside = Lodge::factory()->create();
        $outside = Lodge::factory()->create();
        $requester = $this->directoryUser($requestLodge);
        $group = LodgeGroup::create([
            'lodge_group_type_id' => LodgeGroupType::query()->where('key', 'region')->sole()->id,
            'name' => 'Southwest Indiana', 'slug' => 'southwest-indiana', 'is_active' => true, 'has_public_landing_page' => false,
        ]);
        $group->lodges()->attach([$requestLodge->id, $inside->id]);
        $matching = $this->person($inside, DirectoryVisibilityScope::ParticipatingLodges);
        $this->membership($matching, $requestLodge);
        $this->person($outside, DirectoryVisibilityScope::ParticipatingLodges);

        $result = app(DirectoryAccess::class)->search($requestLodge, DirectoryAudience::ParticipatingLodges, group: 'southwest-indiana');

        $this->assertSame([$matching->id], collect($result->items())->pluck('id')->all());
    }

    public function test_platform_admin_can_browse_cross_lodge_privacy_filtered_directory_without_membership(): void
    {
        $lodge = Lodge::factory()->create();
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $visible = $this->person($lodge, DirectoryVisibilityScope::ParticipatingLodges);
        $hidden = $this->person($lodge, DirectoryVisibilityScope::Hidden);

        $this->actingAs($admin)->get("/lodges/{$lodge->id}/directory?audience=participating_lodges")
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('people.data', 1)
                ->where('people.data.0.id', $visible->id)
                ->missing('people.data.0.is_deceased'));
        $this->assertNotSame($visible->id, $hidden->id);
    }

    private function directoryUser(Lodge $lodge): User
    {
        app(LodgeRoleCatalog::class)->ensureFor($lodge);
        $person = $this->person($lodge, DirectoryVisibilityScope::OwnLodge);
        $user = User::factory()->create(['person_id' => $person->id]);
        $role = Role::query()->where('lodge_id', $lodge->id)->where('name', 'Member')->sole();
        DB::table('lodge_user_roles')->insert(['lodge_id' => $lodge->id, 'user_id' => $user->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function person(Lodge $lodge, DirectoryVisibilityScope $scope): Person
    {
        $person = Person::factory()->create();
        $this->membership($person, $lodge);
        $person->directoryPrivacySetting()->update(['scope' => $scope]);

        return $person;
    }

    private function membership(Person $person, Lodge $lodge): Membership
    {
        return Membership::factory()->create([
            'person_id' => $person->id,
            'lodge_id' => $lodge->id,
            'membership_status_id' => MembershipStatus::query()->where('key', 'active')->sole()->id,
        ]);
    }
}
