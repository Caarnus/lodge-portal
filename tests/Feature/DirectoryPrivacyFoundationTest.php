<?php

namespace Tests\Feature;

use App\Enums\DirectoryVisibilityScope;
use App\Models\Lodge;
use App\Models\Membership;
use App\Models\MembershipCommunicationPreference;
use App\Models\MembershipStatus;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonDirectoryPrivacySetting;
use App\Models\Role;
use App\Services\LodgeRoleCatalog;
use App\Services\PersonMergeService;
use Database\Seeders\PeopleMembershipReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectoryPrivacyFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeopleMembershipReferenceSeeder::class);
    }

    public function test_creating_a_person_materializes_conservative_directory_privacy_defaults(): void
    {
        $person = Person::factory()->create();

        $setting = $person->directoryPrivacySetting()->sole();

        $this->assertSame(DirectoryVisibilityScope::OwnLodge, $setting->scope);
        $this->assertFalse($setting->show_email);
        $this->assertFalse($setting->show_phone);
        $this->assertFalse($setting->show_address);
        $this->assertFalse($setting->show_profile_photo);
        $this->assertFalse($setting->show_degree);
    }

    public function test_directory_privacy_factory_creates_a_person_owned_setting(): void
    {
        $setting = PersonDirectoryPrivacySetting::factory()->create();

        $this->assertSame($setting->person_id, $setting->person->id);
        $this->assertSame(DirectoryVisibilityScope::OwnLodge, $setting->scope);
    }

    public function test_retiring_and_restoring_a_person_removes_then_recreates_conservative_privacy(): void
    {
        $person = Person::factory()->create();
        $person->directoryPrivacySetting()->update([
            'scope' => DirectoryVisibilityScope::ParticipatingLodges,
            'show_email' => true,
        ]);

        $person->delete();

        $this->assertDatabaseMissing('person_directory_privacy_settings', ['person_id' => $person->id]);

        $person->restore();

        $setting = $person->fresh()->directoryPrivacySetting()->sole();
        $this->assertSame(DirectoryVisibilityScope::OwnLodge, $setting->scope);
        $this->assertFalse($setting->show_email);
    }

    public function test_creating_a_membership_materializes_a_matching_communication_preference(): void
    {
        $lodge = Lodge::factory()->create();
        $membership = Membership::factory()->create([
            'lodge_id' => $lodge->id,
            'membership_status_id' => MembershipStatus::query()->where('key', 'active')->sole()->id,
        ]);

        $preference = $membership->communicationPreference()->sole();

        $this->assertSame($lodge->id, $preference->lodge_id);
        $this->assertTrue($preference->receives_lodge_email);
    }

    public function test_communication_preference_factory_uses_its_membership_lodge(): void
    {
        $preference = MembershipCommunicationPreference::factory()->create();

        $this->assertSame($preference->membership->lodge_id, $preference->lodge_id);
    }

    public function test_merge_preserves_survivor_privacy_and_membership_preferences(): void
    {
        $sourceLodge = Lodge::factory()->create();
        $survivorLodge = Lodge::factory()->create();
        $sourceMembership = $this->activeMembership($sourceLodge);
        $survivorMembership = $this->activeMembership($survivorLodge);
        $sourcePersonId = $sourceMembership->person_id;
        $sourceMembership->person->directoryPrivacySetting()->update([
            'scope' => DirectoryVisibilityScope::ParticipatingLodges,
            'show_email' => true,
        ]);
        $survivorMembership->person->directoryPrivacySetting()->update([
            'scope' => DirectoryVisibilityScope::Hidden,
        ]);
        $sourceMembership->communicationPreference()->update(['receives_lodge_email' => false]);

        app(PersonMergeService::class)->merge($sourceMembership->person, $survivorMembership->person);

        $this->assertSoftDeleted('people', ['id' => $sourcePersonId]);
        $this->assertDatabaseMissing('person_directory_privacy_settings', ['person_id' => $sourcePersonId]);
        $this->assertDatabaseHas('person_directory_privacy_settings', [
            'person_id' => $survivorMembership->person_id,
            'scope' => DirectoryVisibilityScope::Hidden->value,
        ]);
        $this->assertDatabaseHas('membership_communication_preferences', [
            'membership_id' => $sourceMembership->id,
            'lodge_id' => $sourceLodge->id,
            'receives_lodge_email' => false,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'person.merged',
            'after->directory_privacy_resolution' => 'survivor_existing',
        ]);
    }

    public function test_merge_uses_conservative_privacy_when_survivor_setting_is_missing(): void
    {
        $sourceMembership = $this->activeMembership(Lodge::factory()->create());
        $survivorMembership = $this->activeMembership(Lodge::factory()->create());
        $sourceMembership->person->directoryPrivacySetting()->update([
            'scope' => DirectoryVisibilityScope::ParticipatingLodges,
            'show_email' => true,
        ]);
        $survivorMembership->person->directoryPrivacySetting()->delete();

        app(PersonMergeService::class)->merge($sourceMembership->person, $survivorMembership->person);

        $this->assertDatabaseHas('person_directory_privacy_settings', [
            'person_id' => $survivorMembership->person_id,
            'scope' => DirectoryVisibilityScope::OwnLodge->value,
            'show_email' => false,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'person.merged',
            'after->directory_privacy_resolution' => 'conservative_default',
        ]);
    }

    public function test_role_catalog_synchronizes_built_in_directory_access_without_changing_custom_roles(): void
    {
        $lodge = Lodge::factory()->create();
        $custom = Role::create(['lodge_id' => $lodge->id, 'name' => 'Directory steward']);
        app(LodgeRoleCatalog::class)->seedPermissions();
        $custom->permissions()->attach(Permission::query()->where('key', 'people.manage')->sole());

        app(LodgeRoleCatalog::class)->ensureFor($lodge);
        app(LodgeRoleCatalog::class)->ensureFor($lodge);

        $member = Role::query()->where('lodge_id', $lodge->id)->where('name', 'Member')->sole();
        $officer = Role::query()->where('lodge_id', $lodge->id)->where('name', 'Officer')->sole();
        $administrator = Role::query()->where('lodge_id', $lodge->id)->where('name', 'Administrator')->sole();

        $this->assertSame(['directory.view'], $member->permissions()->pluck('key')->sort()->values()->all());
        $this->assertContains('directory.view', $officer->permissions()->pluck('key')->all());
        $this->assertContains('people.view', $officer->permissions()->pluck('key')->all());
        $this->assertContains('directory.view', $administrator->permissions()->pluck('key')->all());
        $this->assertSame(['people.manage'], $custom->fresh()->permissions()->pluck('key')->all());
        $this->assertDatabaseCount('roles', 5);
    }

    private function activeMembership(Lodge $lodge): Membership
    {
        return Membership::factory()->create([
            'lodge_id' => $lodge->id,
            'membership_status_id' => MembershipStatus::query()->where('key', 'active')->sole()->id,
        ]);
    }
}
