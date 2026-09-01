<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireEffectiveLodgeModule;
use App\Models\Feature;
use App\Models\Lodge;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\LodgeModuleState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LodgeModuleStateTest extends TestCase
{
    use RefreshDatabase;

    private function userFor(Lodge $lodge, array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['lodge_id' => $lodge->id, 'name' => 'Module manager']);
        $ids = collect($permissions)->map(fn (string $key) => Permission::firstOrCreate(['key' => $key], ['name' => $key])->id);
        $role->permissions()->syncWithoutDetaching($ids);
        DB::table('lodge_user_roles')->insert(['lodge_id' => $lodge->id, 'user_id' => $user->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function module(): Feature
    {
        return Feature::create(['key' => 'test-only-module', 'name' => 'Test-only module', 'description' => 'Fixture only']);
    }

    public function test_availability_and_preference_are_separate_and_resolve_fail_closed(): void
    {
        $lodge = Lodge::factory()->create();
        $module = $this->module();
        $states = app(LodgeModuleState::class);

        $this->assertSame(['is_available' => false, 'is_enabled' => false, 'is_effective' => false], collect($states->resolve($lodge, $module))->only(['is_available', 'is_enabled', 'is_effective'])->all());

        $platform = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($platform)->put("/platform/lodges/{$lodge->id}/modules/{$module->id}", ['is_available' => true])->assertRedirect();
        $this->assertFalse($states->resolve($lodge, $module)['is_effective']);

        $manager = $this->userFor($lodge, ['lodge_modules.manage']);
        $this->actingAs($manager)->put("/lodges/{$lodge->id}/modules/{$module->id}", ['is_enabled' => true])->assertRedirect();
        $this->assertTrue($states->resolve($lodge, $module)['is_effective']);
        $this->assertDatabaseHas('audit_events', ['action' => 'lodge_module.availability_updated', 'actor_id' => $platform->id, 'lodge_id' => $lodge->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'lodge_module.preference_updated', 'actor_id' => $manager->id, 'lodge_id' => $lodge->id]);
    }

    public function test_revoking_availability_preserves_lodge_preference_and_restores_effective_state(): void
    {
        $lodge = Lodge::factory()->create();
        $module = $this->module();
        $platform = User::factory()->create(['is_platform_admin' => true]);
        $manager = $this->userFor($lodge, ['lodge_modules.manage']);

        $this->actingAs($platform)->put("/platform/lodges/{$lodge->id}/modules/{$module->id}", ['is_available' => true]);
        $this->actingAs($manager)->put("/lodges/{$lodge->id}/modules/{$module->id}", ['is_enabled' => true]);
        $this->actingAs($platform)->put("/platform/lodges/{$lodge->id}/modules/{$module->id}", ['is_available' => false]);

        $this->assertDatabaseHas('feature_lodge', ['feature_id' => $module->id, 'lodge_id' => $lodge->id, 'is_available' => false, 'is_enabled' => true]);
        $this->assertFalse(app(LodgeModuleState::class)->isEffective($lodge, $module));

        $this->actingAs($platform)->put("/platform/lodges/{$lodge->id}/modules/{$module->id}", ['is_available' => true]);
        $this->assertTrue(app(LodgeModuleState::class)->isEffective($lodge, $module));
    }

    public function test_lodge_permission_cannot_change_platform_availability_or_another_lodge_preference(): void
    {
        $a = Lodge::factory()->create();
        $b = Lodge::factory()->create();
        $module = $this->module();
        $manager = $this->userFor($a, ['lodge_modules.manage']);

        $this->actingAs($manager)->put("/platform/lodges/{$a->id}/modules/{$module->id}", ['is_available' => true])->assertForbidden();
        $this->actingAs($manager)->put("/lodges/{$b->id}/modules/{$module->id}", ['is_enabled' => true])->assertForbidden();
    }

    public function test_administration_surfaces_expose_separate_state_without_granting_cross_lodge_access(): void
    {
        $a = Lodge::factory()->create();
        $b = Lodge::factory()->create();
        $module = $this->module();
        $platform = User::factory()->create(['is_platform_admin' => true]);
        $manager = $this->userFor($a, ['lodge_modules.manage']);

        $this->actingAs($platform)->get("/platform/lodges/{$a->id}/modules")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('platform/LodgeModules')
                ->where('modules.0.key', $module->key)
                ->where('modules.0.is_available', false)
                ->where('modules.0.is_enabled', false));

        $this->actingAs($manager)->get("/lodges/{$a->id}/modules")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('lodge/Modules')->where('modules.0.is_effective', false));
        $this->actingAs($manager)->get("/lodges/{$b->id}/modules")->assertForbidden();
    }

    public function test_unavailable_module_cannot_be_enabled_and_module_permission_does_not_grant_toggle_authority(): void
    {
        $lodge = Lodge::factory()->create();
        $module = $this->module();
        $manager = $this->userFor($lodge, ['test_module.manage']);

        $this->actingAs($manager)->put("/lodges/{$lodge->id}/modules/{$module->id}", ['is_enabled' => true])->assertForbidden();

        $toggleUser = $this->userFor($lodge, ['lodge_modules.manage']);
        $this->actingAs($toggleUser)->put("/lodges/{$lodge->id}/modules/{$module->id}", ['is_enabled' => true])->assertSessionHasErrors('module');
    }

    public function test_effective_module_middleware_rechecks_the_explicit_route_lodge(): void
    {
        Route::middleware(['web', RequireEffectiveLodgeModule::class.':test-only-module'])->get('/_test/modules/{lodge}', fn () => 'ok');
        $a = Lodge::factory()->create();
        $b = Lodge::factory()->create();
        $module = $this->module();
        $platform = User::factory()->create(['is_platform_admin' => true]);
        $manager = $this->userFor($a, ['lodge_modules.manage']);

        $this->actingAs($platform)->put("/platform/lodges/{$a->id}/modules/{$module->id}", ['is_available' => true]);
        $this->actingAs($manager)->put("/lodges/{$a->id}/modules/{$module->id}", ['is_enabled' => true]);

        $this->get("/_test/modules/{$a->id}")->assertOk();
        $this->get("/_test/modules/{$b->id}")->assertNotFound();
        $this->actingAs($platform)->put("/platform/lodges/{$a->id}/modules/{$module->id}", ['is_available' => false]);
        $this->get("/_test/modules/{$a->id}")->assertNotFound();
    }
}
