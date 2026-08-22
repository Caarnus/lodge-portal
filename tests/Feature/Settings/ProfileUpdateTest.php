<?php

namespace Tests\Feature\Settings;

use App\Models\Person;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use App\Services\SelfProfileService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed_for_a_linked_person(): void
    {
        $user = $this->profileUser();

        $this->actingAs($user)->get('/settings/profile')->assertOk()
            ->assertInertia(fn ($page) => $page->where('profile.email', $user->person->email));
    }

    public function test_profile_page_uses_linked_account_email_when_legacy_person_email_is_missing(): void
    {
        $user = $this->profileUser();
        $user->person->update(['email' => null]);

        $this->actingAs($user)->get('/settings/profile')->assertOk()
            ->assertInertia(fn ($page) => $page->where('profile.email', $user->email));
    }

    public function test_profile_information_updates_canonical_person_data_and_user_display_name(): void
    {
        $user = $this->profileUser();
        $payload = $this->payload($user, [
            'preferred_name' => 'Test',
            'email' => 'test@example.com',
            'phone' => '8125550100',
            'mailing_address_line_1' => '101 Main Street',
            'mailing_city' => 'Evansville',
            'mailing_state' => 'in',
            'mailing_postal_code' => '47708',
        ]);

        $this->actingAs($user)->patch('/settings/profile', $payload)
            ->assertSessionHasNoErrors()->assertRedirect('/settings/profile');

        $person = $user->person->fresh();
        $this->assertSame('Test', $person->preferred_name);
        $this->assertSame('test@example.com', $person->email);
        $this->assertSame('(812)555-0100', $person->phone);
        $this->assertSame('IN', $person->mailing_state);
        $this->assertSame('Test '.$person->legal_last_name, $user->fresh()->name);
        $this->assertSame('test@example.com', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'profile.updated',
            'subject_id' => $person->id,
            'after->email_changed' => true,
        ]);
    }

    public function test_email_change_reverifies_only_after_successful_commit(): void
    {
        Notification::fake();
        $user = $this->profileUser();

        $this->actingAs($user)->patch('/settings/profile', $this->payload($user, ['email' => 'changed@example.test']))
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user->fresh(), QueuedVerifyEmail::class);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame('changed@example.test', $user->person->fresh()->email);
    }

    public function test_email_verification_status_is_unchanged_when_email_is_unchanged(): void
    {
        Notification::fake();
        $user = $this->profileUser();

        $this->actingAs($user)->patch('/settings/profile', $this->payload($user))
            ->assertSessionHasNoErrors()->assertRedirect('/settings/profile');

        $this->assertNotNull($user->fresh()->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_profile_rejects_locked_and_identity_reassignment_fields(): void
    {
        $user = $this->profileUser();
        $person = $user->person;
        $other = Person::factory()->create();

        $this->actingAs($user)->patch('/settings/profile', $this->payload($user, [
            'person_id' => $other->id,
            'legal_first_name' => 'Changed',
            'approval_status' => 'pending',
        ]))->assertSessionHasErrors(['person_id', 'legal_first_name', 'approval_status']);

        $this->assertSame($person->id, $user->fresh()->person_id);
        $this->assertNotSame('Changed', $person->fresh()->legal_first_name);
        $this->assertSame('approved', $user->fresh()->approval_status);
    }

    public function test_profile_rejects_email_used_by_a_different_person_or_user_without_partial_write(): void
    {
        $user = $this->profileUser();
        $personConflict = Person::factory()->create(['email' => 'person-conflict@example.test']);
        $userConflict = User::factory()->create(['email' => 'user-conflict@example.test']);

        foreach ([$personConflict->email, $userConflict->email] as $email) {
            $this->actingAs($user)->patch('/settings/profile', $this->payload($user, [
                'preferred_name' => 'Would Change',
                'email' => $email,
            ]))->assertSessionHasErrors('email');

            $this->assertNotSame('Would Change', $user->person->fresh()->preferred_name);
            $this->assertSame($user->email, $user->fresh()->email);
            $this->assertSame($user->email, $user->person->fresh()->email);
        }
    }

    public function test_unlinked_merged_deceased_and_retired_people_cannot_use_profile_settings(): void
    {
        $unlinked = User::factory()->create();
        $merged = $this->profileUser();
        $deceased = $this->profileUser();
        $retired = $this->profileUser();
        $merged->person->update(['merged_at' => now()]);
        $deceased->person->update(['is_deceased' => true]);
        $retired->person->delete();

        foreach ([$unlinked, $merged, $deceased, $retired] as $user) {
            $this->actingAs($user)->get('/settings/profile')->assertForbidden();
            $this->actingAs($user)->patch('/settings/profile', $this->payload($user))->assertForbidden();
        }
    }

    public function test_profile_service_rejects_a_stale_account_link_before_writing(): void
    {
        $user = $this->profileUser();
        $originalPerson = $user->person;
        $replacement = Person::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['person_id' => $replacement->id]);

        $this->expectException(AuthorizationException::class);

        try {
            app(SelfProfileService::class)->update($user, $this->payload($user, [
                'preferred_name' => 'Would Change',
            ]));
        } finally {
            $this->assertNotSame('Would Change', $originalPerson->fresh()->preferred_name);
            $this->assertNotSame('Would Change', $replacement->fresh()->preferred_name);
        }
    }

    public function test_user_can_delete_their_linked_account_without_deleting_person(): void
    {
        $user = $this->profileUser();
        $person = $user->person;

        $this->actingAs($user)->delete('/settings/profile', ['password' => 'password'])
            ->assertSessionHasNoErrors()->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
        $this->assertDatabaseHas('people', ['id' => $person->id]);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = $this->profileUser();

        $this->actingAs($user)->from('/settings/profile')->delete('/settings/profile', ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password')->assertRedirect('/settings/profile');

        $this->assertNotNull($user->fresh());
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

    private function payload(User $user, array $overrides = []): array
    {
        $person = $user->person;

        return array_merge([
            'preferred_name' => $person?->preferred_name,
            'email' => $person?->email ?? $user->email,
            'phone' => $person?->phone,
            'mailing_address_line_1' => $person?->mailing_address_line_1,
            'mailing_address_line_2' => $person?->mailing_address_line_2,
            'mailing_city' => $person?->mailing_city,
            'mailing_state' => $person?->mailing_state,
            'mailing_postal_code' => $person?->mailing_postal_code,
        ], $overrides);
    }
}
