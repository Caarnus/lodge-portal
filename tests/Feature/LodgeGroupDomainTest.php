<?php

namespace Tests\Feature;

use App\Models\Lodge;
use App\Models\LodgeGroupType;
use App\Models\User;
use App\Services\LodgeGroupService;
use App\Services\LodgeGroupTypeService;
use Database\Seeders\LodgeGroupTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LodgeGroupDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_types_seed_idempotently_with_stable_initial_values(): void
    {
        $this->seed(LodgeGroupTypeSeeder::class);
        $this->seed(LodgeGroupTypeSeeder::class);

        $this->assertDatabaseCount('lodge_group_types', 5);
        $this->assertDatabaseHas('lodge_group_types', ['key' => 'region', 'name' => 'Region', 'is_active' => true]);
    }

    public function test_groups_support_overlapping_memberships_and_record_bounded_audit_data(): void
    {
        $this->seed(LodgeGroupTypeSeeder::class);
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $a = Lodge::factory()->create();
        $b = Lodge::factory()->create();
        $service = app(LodgeGroupService::class);
        $region = $service->create($this->groupData('Southwest Indiana'), $admin);
        $county = $service->create($this->groupData('Warrick County'), $admin);

        $service->synchronizeLodges($region, [$a->id, $b->id], $admin);
        $service->synchronizeLodges($county, [$a->id], $admin);

        $this->assertSame([$region->id, $county->id], $a->lodgeGroups()->orderBy('lodge_groups.id')->pluck('lodge_groups.id')->all());
        $this->assertDatabaseCount('lodge_group_memberships', 3);
        $this->assertDatabaseHas('audit_events', ['action' => 'lodge_group.memberships_synchronized', 'subject_id' => $region->id]);
    }

    public function test_archived_groups_are_read_only_but_restorable_and_keep_memberships(): void
    {
        $this->seed(LodgeGroupTypeSeeder::class);
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $lodge = Lodge::factory()->create();
        $service = app(LodgeGroupService::class);
        $group = $service->create($this->groupData('Southwest Indiana'), $admin);
        $service->synchronizeLodges($group, [$lodge->id], $admin);
        $service->archive($group, $admin);

        try {
            $service->synchronizeLodges($group->fresh(), [], $admin);
            $this->fail('Archived group accepted membership changes.');
        } catch (ValidationException) {
        } finally {
            $this->assertDatabaseHas('lodge_group_memberships', ['lodge_group_id' => $group->id, 'lodge_id' => $lodge->id]);
        }

        $restored = $service->restore($group->fresh(), $admin);
        $this->assertNull($restored->archived_at);
    }

    public function test_inactive_type_stays_on_existing_group_but_cannot_be_selected_or_deleted_when_referenced(): void
    {
        $this->seed(LodgeGroupTypeSeeder::class);
        $type = LodgeGroupType::query()->where('key', 'region')->sole();
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $groupService = app(LodgeGroupService::class);
        $group = $groupService->create($this->groupData('Southwest Indiana'), $admin);
        $type->update(['is_active' => false]);

        $updated = $groupService->update($group, $this->groupData('Southwest Indiana') + ['lodge_group_type_id' => $type->id], $admin);
        $this->assertSame($type->id, $updated->lodge_group_type_id);

        $this->expectException(ValidationException::class);
        app(LodgeGroupTypeService::class)->delete($type);
    }

    private function groupData(string $name): array
    {
        return [
            'lodge_group_type_id' => LodgeGroupType::query()->where('key', 'region')->sole()->id,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'description' => 'Group description',
            'is_active' => true,
            'has_public_landing_page' => false,
        ];
    }
}
