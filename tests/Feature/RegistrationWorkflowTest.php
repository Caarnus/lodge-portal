<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Lodge;
use App\Models\Permission;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use App\Notifications\RegistrationDecision;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function register(Lodge $lodge, string $email = 'candidate@example.com')
    {
        return $this->post('/register', [
            'name' => 'Candidate User',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
            'home_lodge_id' => $lodge->id,
        ]);
    }

    private function reviewerFor(Lodge $lodge, array $permissionKeys = ['registration.review']): User
    {
        $user = User::factory()->create();
        $role = Role::create(['lodge_id' => $lodge->id, 'name' => 'Reviewer']);
        foreach ($permissionKeys as $key) {
            $permission = Permission::firstOrCreate(['key' => $key], ['name' => $key]);
            $role->permissions()->attach($permission);
        }
        DB::table('lodge_user_roles')->insert(['lodge_id' => $lodge->id, 'user_id' => $user->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    public function test_registration_queues_verification_and_routes_to_pending_without_granting_access(): void
    {
        Notification::fake();
        $lodge = Lodge::factory()->create();

        $this->register($lodge)->assertRedirect(route('pending', absolute: false));

        $user = User::where('email', 'candidate@example.com')->firstOrFail();
        Notification::assertSentTo($user, QueuedVerifyEmail::class, fn ($notification) => $notification instanceof ShouldQueue);
        $this->assertSame('pending', $user->approval_status);
        $this->assertFalse($user->hasLodgePermission($lodge, 'lodge.manage'));
        $this->actingAs($user)->get('/pending')->assertOk();
        $this->actingAs($user)->get('/settings/profile')->assertRedirect(route('verification.notice', absolute: false));

        $user->markEmailAsVerified();
        $this->actingAs($user)->get('/settings/profile')->assertRedirect(route('pending', absolute: false));
    }

    public function test_registration_may_select_only_an_active_lodge(): void
    {
        $disabled = Lodge::factory()->create(['status' => 'disabled']);

        $this->register($disabled)->assertSessionHasErrors('home_lodge_id');

        $this->assertDatabaseMissing('users', ['email' => 'candidate@example.com']);
    }

    public function test_registration_links_only_one_unambiguous_case_insensitive_person_match(): void
    {
        $lodge = Lodge::factory()->create();
        $person = Person::create(['name' => 'Candidate', 'email' => 'Candidate@Example.com']);

        $this->register($lodge)->assertRedirect();
        $this->assertSame($person->id, User::where('email', 'candidate@example.com')->value('person_id'));
    }

    public function test_registration_without_a_person_match_does_not_create_an_incorrect_link(): void
    {
        $lodge = Lodge::factory()->create();
        Person::create(['name' => 'Different Person', 'email' => 'different@example.com']);

        $this->register($lodge)->assertRedirect();
        $this->assertNull(User::where('email', 'candidate@example.com')->value('person_id'));
    }

    public function test_reviewer_can_decide_only_home_lodge_registrations_and_decision_is_audited_and_queued(): void
    {
        Notification::fake();
        $a = Lodge::factory()->create();
        $b = Lodge::factory()->create();
        $reviewer = $this->reviewerFor($a);
        $candidateA = User::factory()->create(['home_lodge_id' => $a->id, 'approval_status' => 'pending', 'approved_at' => null]);
        $candidateB = User::factory()->create(['home_lodge_id' => $b->id, 'approval_status' => 'pending', 'approved_at' => null]);

        $this->actingAs($reviewer)->patch("/registrations/{$candidateB->id}", ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($reviewer)->patch("/registrations/{$candidateA->id}", ['decision' => 'approved'])->assertRedirect();

        $this->assertSame('approved', $candidateA->fresh()->approval_status);
        $event = AuditEvent::where('action', 'registration.approved')->firstOrFail();
        $this->assertSame($reviewer->id, $event->actor_id);
        $this->assertSame($a->id, $event->lodge_id);
        $this->assertSame('pending', $event->before['approval_status']);
        $this->assertSame('approved', $event->after['approval_status']);
        Notification::assertSentTo($candidateA, RegistrationDecision::class, fn ($notification) => $notification instanceof ShouldQueue && $notification->lodgeName === $a->name);
        Notification::assertNothingSentTo($candidateB);
    }

    public function test_platform_admin_can_reject_any_registration_with_a_reason(): void
    {
        Notification::fake();
        $lodge = Lodge::factory()->create();
        $platform = User::factory()->create(['is_platform_admin' => true]);
        $candidate = User::factory()->create(['home_lodge_id' => $lodge->id, 'approval_status' => 'pending', 'approved_at' => null]);

        $this->actingAs($platform)->patch("/registrations/{$candidate->id}", ['decision' => 'rejected', 'reason' => 'Membership could not be confirmed.'])->assertRedirect();

        $candidate->refresh();
        $this->assertSame('rejected', $candidate->approval_status);
        $this->assertSame('Membership could not be confirmed.', $candidate->rejection_reason);
        Notification::assertSentTo($candidate, RegistrationDecision::class);
    }

    public function test_user_without_review_permission_cannot_open_registration_queue(): void
    {
        $lodge = Lodge::factory()->create();
        $manager = $this->reviewerFor($lodge, ['lodge.manage']);

        $this->actingAs($manager)->get('/registrations')->assertForbidden();
    }
}
