<?php

namespace Tests\Feature\Settings;

use App\Jobs\ProcessProfilePhoto;
use App\Models\Lodge;
use App\Models\Membership;
use App\Models\MembershipStatus;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_updates_only_own_directory_privacy_with_explicit_values_and_audit(): void
    {
        $user = $this->profileUser();

        $this->actingAs($user)->put('/settings/profile/directory-privacy', [
            'scope' => 'participating_lodges',
            'show_email' => true,
            'show_phone' => false,
            'show_address' => true,
            'show_profile_photo' => true,
            'show_degree' => false,
            'person_id' => Person::factory()->create()->id,
        ])->assertRedirect('/settings/profile')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('person_directory_privacy_settings', [
            'person_id' => $user->person_id,
            'scope' => 'participating_lodges',
            'show_email' => true,
            'show_address' => true,
            'updated_by' => $user->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'directory_privacy.updated',
            'subject_id' => $user->person_id,
            'actor_id' => $user->id,
        ]);
    }

    public function test_directory_privacy_requires_known_scope_and_all_boolean_choices(): void
    {
        $user = $this->profileUser();

        $this->actingAs($user)->put('/settings/profile/directory-privacy', [
            'scope' => 'everyone',
            'show_email' => 'not-a-boolean',
        ])->assertSessionHasErrors(['scope', 'show_email', 'show_phone', 'show_address', 'show_profile_photo', 'show_degree']);
    }

    public function test_member_updates_only_an_active_own_membership_communication_preference(): void
    {
        $user = $this->profileUser();
        $membership = $this->activeMembership($user->person_id);

        $this->actingAs($user)->put("/settings/profile/memberships/{$membership->id}/communication-preference", [
            'receives_lodge_email' => false,
            'person_id' => Person::factory()->create()->id,
            'lodge_id' => Lodge::factory()->create()->id,
        ])->assertRedirect('/settings/profile')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('membership_communication_preferences', [
            'membership_id' => $membership->id,
            'lodge_id' => $membership->lodge_id,
            'receives_lodge_email' => false,
            'updated_by' => $user->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'membership_communication_preference.updated',
            'actor_id' => $user->id,
            'lodge_id' => $membership->lodge_id,
        ]);
    }

    public function test_member_cannot_change_someone_elses_or_ended_membership_preference(): void
    {
        $user = $this->profileUser();
        $other = $this->activeMembership(Person::factory()->create()->id);
        $ended = $this->activeMembership($user->person_id, ['end_date' => now()]);

        foreach ([$other, $ended] as $membership) {
            $this->actingAs($user)->put("/settings/profile/memberships/{$membership->id}/communication-preference", [
                'receives_lodge_email' => false,
            ])->assertForbidden();
        }
    }

    public function test_communication_preference_requires_a_boolean(): void
    {
        $user = $this->profileUser();
        $membership = $this->activeMembership($user->person_id);

        $this->actingAs($user)->put("/settings/profile/memberships/{$membership->id}/communication-preference", [
            'receives_lodge_email' => 'sometimes',
        ])->assertSessionHasErrors('receives_lodge_email');
    }

    public function test_member_photo_is_private_ready_only_and_removable(): void
    {
        Storage::fake('local');
        $user = $this->profileUser();

        $this->actingAs($user)->post('/settings/profile/photo', [
            'photo' => UploadedFile::fake()->image('portrait.jpg', 400, 500),
        ])->assertRedirect('/settings/profile')->assertSessionHasNoErrors();

        $person = $user->person->fresh();
        $this->assertSame('ready', $person->profile_photo_status);
        $response = $this->actingAs($user)->get('/settings/profile/photo')->assertOk();
        $this->assertStringContainsString('private', $response->headers->get('cache-control'));
        $this->assertStringContainsString('no-store', $response->headers->get('cache-control'));

        $this->actingAs($user)->delete('/settings/profile/photo')->assertRedirect('/settings/profile');
        $this->actingAs($user)->get('/settings/profile/photo')->assertNotFound();
        $this->assertDatabaseHas('audit_events', ['action' => 'person.photo_removed', 'subject_id' => $person->id]);
    }

    public function test_photo_job_does_not_write_a_derivative_for_a_retired_person(): void
    {
        Storage::fake('local');
        $person = Person::factory()->create([
            'profile_photo_path' => 'profile-originals/1/source.jpg',
            'profile_photo_status' => 'pending',
        ]);
        Storage::disk('local')->put($person->profile_photo_path, 'not-an-image');
        $person->delete();

        (new ProcessProfilePhoto($person->id, 'profile-originals/1/source.jpg'))->handle();

        $this->assertDatabaseMissing('people', ['id' => $person->id, 'profile_photo_status' => 'ready']);
        Storage::disk('local')->assertMissing('profile-photos/'.$person->id);
    }

    private function profileUser(): User
    {
        $person = Person::factory()->create();

        return User::factory()->create([
            'person_id' => $person->id,
            'name' => $person->display_name,
            'email' => $person->email,
        ]);
    }

    private function activeMembership(int $personId, array $attributes = []): Membership
    {
        $status = MembershipStatus::query()->firstOrCreate(['key' => 'active'], [
            'name' => 'Active',
            'is_default' => true,
        ]);

        return Membership::factory()->create([
            'person_id' => $personId,
            'membership_status_id' => $status->id,
            ...$attributes,
        ]);
    }
}
