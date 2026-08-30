<?php

namespace Tests\Feature;

use App\Models\Lodge;
use App\Models\LodgeGroup;
use App\Models\LodgeGroupType;
use App\Models\User;
use Database\Seeders\LodgeGroupTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformLodgeGroupManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_manages_groups_types_memberships_and_archive_lifecycle(): void
    {
        $this->seed(LodgeGroupTypeSeeder::class);
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $a = Lodge::factory()->create();
        $b = Lodge::factory()->create();
        $type = LodgeGroupType::query()->where('key', 'region')->sole();
        $payload = [
            'lodge_group_type_id' => $type->id,
            'name' => 'Southwest Indiana',
            'slug' => 'southwest-indiana',
            'description' => 'Regional coordination filter.',
            'is_active' => true,
            'has_public_landing_page' => true,
        ];

        $this->actingAs($admin)->post('/platform/lodge-groups', $payload)->assertRedirect();
        $group = LodgeGroup::query()->where('slug', 'southwest-indiana')->sole();
        $this->actingAs($admin)->put("/platform/lodge-groups/{$group->id}/lodges", ['lodge_ids' => [$a->id, $b->id]])->assertRedirect();
        $this->assertDatabaseCount('lodge_group_memberships', 2);
        $this->actingAs($admin)->patch("/platform/lodge-groups/{$group->id}/archive")->assertRedirect();
        $this->assertNotNull($group->fresh()->archived_at);
        $this->actingAs($admin)->patch("/platform/lodge-groups/{$group->id}/restore")->assertRedirect();
        $this->assertNull($group->fresh()->archived_at);

        $this->actingAs($admin)->post('/platform/lodge-group-types', [
            'name' => 'Metro Area',
            'key' => 'metro_area',
            'description' => 'Metropolitan grouping.',
            'sort_order' => 50,
        ])->assertRedirect();
        $this->assertDatabaseHas('lodge_group_types', ['key' => 'metro_area', 'is_active' => true]);
    }

    public function test_non_platform_user_cannot_manage_lodge_groups_or_types(): void
    {
        $this->seed(LodgeGroupTypeSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/platform/lodge-groups')->assertForbidden();
        $this->actingAs($user)->post('/platform/lodge-group-types', ['name' => 'Denied'])->assertForbidden();
    }

    public function test_group_validation_reports_slug_conflicts_and_inactive_type_selection(): void
    {
        $this->seed(LodgeGroupTypeSeeder::class);
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $type = LodgeGroupType::query()->where('key', 'region')->sole();
        LodgeGroup::create([
            'lodge_group_type_id' => $type->id,
            'name' => 'Southwest Indiana',
            'slug' => 'southwest-indiana',
            'is_active' => true,
            'has_public_landing_page' => false,
        ]);
        $type->update(['is_active' => false]);

        $this->actingAs($admin)->post('/platform/lodge-groups', [
            'lodge_group_type_id' => $type->id,
            'name' => 'Southwest Indiana Two',
            'slug' => 'southwest-indiana',
            'is_active' => true,
            'has_public_landing_page' => false,
        ])->assertSessionHasErrors('slug');
        $this->actingAs($admin)->post('/platform/lodge-groups', [
            'lodge_group_type_id' => $type->id,
            'name' => 'Southwest Indiana Two',
            'slug' => 'southwest-indiana-two',
            'is_active' => true,
            'has_public_landing_page' => false,
        ])->assertSessionHasErrors('lodge_group_type_id');
    }
}
