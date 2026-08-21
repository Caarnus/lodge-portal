<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Feature;
use App\Models\Lodge;
use App\Models\Permission;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Models\WebsitePage;
use App\Notifications\QueuedResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformFoundationTenancyTest extends TestCase
{
    use RefreshDatabase;

    private function payload(Lodge $l, array $changes = []): array
    {
        return array_merge($l->only(['name', 'number', 'slug', 'city', 'state', 'jurisdiction', 'physical_address', 'mailing_address', 'meeting_location', 'timezone', 'public_email', 'public_phone', 'primary_color', 'secondary_color']), ['status' => $l->status->value], $changes);
    }

    private function adminFor(Lodge $l, array $permissionKeys = ['lodge.manage']): User
    {
        $u = User::factory()->create();
        $permissions = collect($permissionKeys)->map(fn (string $key) => Permission::firstOrCreate(['key' => $key], ['name' => $key]));
        $r = Role::firstOrCreate(['lodge_id' => $l->id, 'name' => 'Administrator']);
        $r->permissions()->syncWithoutDetaching($permissions->pluck('id'));
        DB::table('lodge_user_roles')->insert(['lodge_id' => $l->id, 'user_id' => $u->id, 'role_id' => $r->id, 'created_at' => now(), 'updated_at' => now()]);

        return $u;
    }

    public function test_platform_admin_can_create_lodge_and_action_is_audited(): void
    {
        $u = User::factory()->create(['is_platform_admin' => true]);
        $data = ['name' => 'Alpha Lodge', 'number' => '1', 'slug' => 'alpha', 'city' => 'Evansville', 'state' => 'IN', 'jurisdiction' => 'Indiana', 'physical_address' => '1 Main St', 'timezone' => 'America/Chicago', 'public_email' => 'alpha@example.com', 'status' => 'active', 'primary_color' => '#1E3A5F', 'secondary_color' => '#D4AF37'];
        $this->actingAs($u)->post('/platform/lodges', $data)->assertRedirect();
        $this->assertDatabaseHas('lodges', ['slug' => 'alpha']);
        $this->assertDatabaseHas('audit_events', ['action' => 'lodge.created', 'actor_id' => $u->id]);
    }

    public function test_lodge_admin_cannot_update_another_lodge_even_when_current_context_is_changed(): void
    {
        $a = Lodge::factory()->create();
        $b = Lodge::factory()->create();
        $u = $this->adminFor($a);
        $u->update(['current_lodge_id' => $b->id]);
        $this->actingAs($u)->put("/lodges/{$b->id}/settings", $this->payload($b, ['name' => 'Compromised']))->assertForbidden();
        $this->assertNotSame('Compromised', $b->fresh()->name);
    }

    public function test_lodge_admin_can_update_and_upload_a_logo_only_for_own_lodge(): void
    {
        Storage::fake('public');
        $a = Lodge::factory()->create();
        $b = Lodge::factory()->create();
        $u = $this->adminFor($a);

        $this->actingAs($u)->put("/lodges/{$a->id}/settings", $this->payload($a, [
            'name' => 'Updated Lodge A',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]))->assertRedirect();

        $logoPath = $a->fresh()->logo_path;
        $this->assertSame('Updated Lodge A', $a->fresh()->name);
        $this->assertNotNull($logoPath);
        Storage::disk('public')->assertExists($logoPath);

        $this->actingAs($u)->put("/lodges/{$b->id}/settings", $this->payload($b, [
            'logo' => UploadedFile::fake()->image('other.png'),
        ]))->assertForbidden();
        $this->assertNull($b->fresh()->logo_path);
    }

    public function test_multi_lodge_administrator_has_only_explicit_permissions(): void
    {
        $a = Lodge::factory()->create();
        $b = Lodge::factory()->create();
        $c = Lodge::factory()->create();
        $u = $this->adminFor($a);
        $permission = Permission::firstOrCreate(['key' => 'lodge.manage'], ['name' => 'Manage lodge']);
        $role = Role::create(['lodge_id' => $b->id, 'name' => 'Administrator']);
        $role->permissions()->attach($permission);
        DB::table('lodge_user_roles')->insert(['lodge_id' => $b->id, 'user_id' => $u->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($u)->get("/lodges/{$a->id}/settings")->assertOk();
        $this->actingAs($u)->get("/lodges/{$b->id}/settings")->assertOk();
        $this->actingAs($u)->get("/lodges/{$c->id}/settings")->assertForbidden();

        $this->actingAs($u)->post("/lodges/{$b->id}/activate")->assertRedirect();
        $this->assertSame($b->id, $u->fresh()->current_lodge_id);
        $this->actingAs($u)->post("/lodges/{$c->id}/activate")->assertForbidden();
    }

    public function test_database_rejects_a_role_assignment_with_a_mismatched_lodge(): void
    {
        $a = Lodge::factory()->create();
        $b = Lodge::factory()->create();
        $user = User::factory()->create();
        $role = Role::create(['lodge_id' => $a->id, 'name' => 'Administrator']);

        $this->expectException(QueryException::class);
        DB::table('lodge_user_roles')->insert([
            'lodge_id' => $b->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_disabled_lodge_can_be_reactivated_by_its_administrator(): void
    {
        $lodge = Lodge::factory()->create(['status' => 'disabled']);
        $u = $this->adminFor($lodge);

        $this->actingAs($u)->put("/lodges/{$lodge->id}/settings", $this->payload($lodge, ['status' => 'active']))->assertRedirect();

        $this->assertSame('active', $lodge->fresh()->status->value);
        $this->assertDatabaseHas('audit_events', ['action' => 'lodge.updated', 'actor_id' => $u->id, 'lodge_id' => $lodge->id]);
    }

    public function test_locked_lodge_requires_platform_admin_to_reactivate(): void
    {
        $l = Lodge::factory()->create(['status' => 'disabled_locked']);
        $u = $this->adminFor($l);
        $this->actingAs($u)->put("/lodges/{$l->id}/settings", $this->payload($l, ['status' => 'active']))->assertForbidden();

        $platform = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($platform)->patch("/platform/lodges/{$l->id}", $this->payload($l, ['status' => 'active']))->assertRedirect();
        $this->assertSame('active', $l->fresh()->status->value);
    }

    public function test_audit_events_are_immutable(): void
    {
        $e = AuditEvent::create(['action' => 'test']);
        $this->expectException(\LogicException::class);
        $e->update(['action' => 'changed']);
    }

    public function test_platform_admin_can_assign_an_existing_user_as_lodge_admin(): void
    {
        $l = Lodge::factory()->create();
        $platform = User::factory()->create(['is_platform_admin' => true]);
        $user = User::factory()->create();
        Permission::create(['key' => 'lodge.manage', 'name' => 'Manage lodge']);
        Permission::create(['key' => 'registration.review', 'name' => 'Review registration']);
        $this->actingAs($platform)->post("/platform/lodges/{$l->id}/admins", ['email' => $user->email])->assertRedirect();
        $this->assertTrue($user->fresh()->hasLodgePermission($l, 'lodge.manage'));
        $this->assertDatabaseHas('audit_events', ['action' => 'lodge.admin_assigned', 'lodge_id' => $l->id]);
    }

    public function test_platform_admin_can_remove_an_account_without_removing_the_person_record(): void
    {
        $platform = User::factory()->create(['is_platform_admin' => true]);
        $lodge = Lodge::factory()->create();
        $person = Person::factory()->create();
        $account = User::factory()->create(['person_id' => $person->id]);
        $role = Role::create(['lodge_id' => $lodge->id, 'name' => 'Member']);
        DB::table('lodge_user_roles')->insert([
            'lodge_id' => $lodge->id,
            'user_id' => $account->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($platform)->delete("/platform/accounts/{$account->id}")->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $account->id]);
        $this->assertDatabaseMissing('lodge_user_roles', ['user_id' => $account->id]);
        $this->assertDatabaseHas('people', ['id' => $person->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'user.deleted', 'actor_id' => $platform->id]);
    }

    public function test_platform_admin_cannot_remove_their_own_account(): void
    {
        $platform = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($platform)->delete("/platform/accounts/{$platform->id}")
            ->assertSessionHasErrors('account');

        $this->assertDatabaseHas('users', ['id' => $platform->id]);
    }

    public function test_lodge_edit_lists_each_administrator_once_and_excludes_non_administrators(): void
    {
        $lodge = Lodge::factory()->create();
        $platform = User::factory()->create(['is_platform_admin' => true]);
        $administrator = User::factory()->create();
        $nonAdministrator = User::factory()->create();
        $administratorRole = Role::create(['lodge_id' => $lodge->id, 'name' => 'Administrator']);
        $officerRole = Role::create(['lodge_id' => $lodge->id, 'name' => 'Officer']);

        foreach ([
            [$administrator->id, $administratorRole->id],
            [$administrator->id, $officerRole->id],
            [$nonAdministrator->id, $officerRole->id],
        ] as [$userId, $roleId]) {
            DB::table('lodge_user_roles')->insert([
                'lodge_id' => $lodge->id,
                'user_id' => $userId,
                'role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($platform)->get("/platform/lodges/{$lodge->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/LodgeForm')
                ->has('admins', 1)
                ->where('admins.0.id', $administrator->id)
                ->where('admins.0.email', $administrator->email));
    }

    public function test_platform_admin_can_invite_a_new_lodge_admin_with_queued_password_setup(): void
    {
        Notification::fake();
        $lodge = Lodge::factory()->create();
        $platform = User::factory()->create(['is_platform_admin' => true]);
        Permission::create(['key' => 'lodge.manage', 'name' => 'Manage lodge']);
        Permission::create(['key' => 'registration.review', 'name' => 'Review registration']);

        $this->actingAs($platform)->post("/platform/lodges/{$lodge->id}/admins", [
            'name' => 'Invited Admin',
            'email' => 'invited@example.test',
        ])->assertRedirect();

        $invited = User::where('email', 'invited@example.test')->firstOrFail();
        $this->assertSame('approved', $invited->approval_status);
        $this->assertTrue($invited->hasLodgePermission($lodge, 'lodge.manage'));
        Notification::assertSentTo($invited, QueuedResetPassword::class, fn ($notification) => $notification instanceof ShouldQueue);
    }

    public function test_platform_admin_can_manage_existing_feature_assignments(): void
    {
        $lodge = Lodge::factory()->create();
        $feature = Feature::create(['key' => 'test-feature', 'name' => 'Test feature']);
        $platform = User::factory()->create(['is_platform_admin' => true]);
        $lodgeAdmin = $this->adminFor($lodge);

        $this->actingAs($platform)->put("/platform/lodges/{$lodge->id}/features", ['features' => [$feature->id]])->assertRedirect();
        $this->assertDatabaseHas('feature_lodge', ['lodge_id' => $lodge->id, 'feature_id' => $feature->id, 'enabled' => true]);

        $this->actingAs($lodgeAdmin)->put("/platform/lodges/{$lodge->id}/features", ['features' => []])->assertForbidden();
    }

    public function test_platform_admin_command_is_idempotent(): void
    {
        $this->artisan('platform:admin admin@example.test --name="First Admin" --password="a-secure-password"')->assertSuccessful();
        $this->artisan('platform:admin admin@example.test --name="Updated Admin" --password="another-secure-password"')->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $admin = User::firstOrFail();
        $this->assertSame('Updated Admin', $admin->name);
        $this->assertTrue($admin->is_platform_admin);
        $this->assertSame('approved', $admin->approval_status);
        $this->assertNotNull($admin->email_verified_at);
    }

    public function test_platform_admin_creation_does_not_grant_lodge_membership(): void
    {
        $platform = User::factory()->create(['is_platform_admin' => true]);
        $data = ['name' => 'Alpha Lodge', 'number' => '1', 'slug' => 'alpha', 'city' => 'Evansville', 'state' => 'IN', 'jurisdiction' => 'Indiana', 'physical_address' => '1 Main St', 'timezone' => 'America/Chicago', 'public_email' => 'alpha@example.com', 'status' => 'active', 'primary_color' => '#1E3A5F', 'secondary_color' => '#D4AF37'];

        $this->actingAs($platform)->post('/platform/lodges', $data)->assertRedirect();

        $this->assertDatabaseCount('lodge_user_roles', 0);
    }

    public function test_lodge_list_links_only_to_reachable_published_sites(): void
    {
        $platform = User::factory()->create(['is_platform_admin' => true]);
        $published = Lodge::factory()->create(['name' => 'Alpha', 'slug' => 'alpha', 'status' => 'active']);
        Lodge::factory()->create(['name' => 'Beta', 'slug' => 'beta', 'status' => 'active']);
        $disabled = Lodge::factory()->create(['name' => 'Gamma', 'slug' => 'gamma', 'status' => 'disabled']);

        foreach ([$published, $disabled] as $lodge) {
            WebsitePage::create(['lodge_id' => $lodge->id])->versions()->create([
                'lodge_id' => $lodge->id,
                'status' => 'published',
                'title' => 'Home',
                'slug' => 'home',
                'is_home' => true,
                'show_in_navigation' => true,
                'navigation_order' => 0,
                'created_by' => $platform->id,
            ]);
        }

        $this->actingAs($platform)->get('/platform/lodges')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('platform/Lodges')
            ->has('lodges', 3)
            ->where('lodges.0.public_site_url', '/l/alpha')
            ->where('lodges.1.public_site_url', null)
            ->where('lodges.2.public_site_url', null));
    }

    public function test_administrator_two_factor_policy_can_be_enabled_or_disabled(): void
    {
        $platform = User::factory()->create(['is_platform_admin' => true]);

        config(['security.admin_2fa_required' => false]);
        $this->actingAs($platform)->get('/platform/lodges')->assertOk();

        config(['security.admin_2fa_required' => true]);
        $this->actingAs($platform)->get('/platform/lodges')->assertRedirect(route('two-factor.settings', absolute: false));

        $platform->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->actingAs($platform)->get('/platform/lodges')->assertOk();
    }
}
