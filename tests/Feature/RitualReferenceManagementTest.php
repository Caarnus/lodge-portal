<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonRitualProficiency;
use App\Models\RitualCategory;
use App\Models\RitualPart;
use App\Models\User;
use Database\Seeders\PeopleMembershipReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\TestCase;

class RitualReferenceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void { parent::setUp(); $this->withoutMiddleware(PreventRequestForgery::class); $this->seed(PeopleMembershipReferenceSeeder::class); }

    public function test_platform_admin_can_create_reference_records_but_member_cannot(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin)->post('/platform/ritual-categories', ['name' => 'Floor Work'])->assertRedirect();
        $category = RitualCategory::where('key', 'floor_work')->sole();
        $this->actingAs($admin)->post('/platform/ritual-parts', ['ritual_category_id' => $category->id, 'name' => 'Opening', 'counts_toward_program' => true, 'point_value' => 25])->assertRedirect();
        $this->actingAs($admin)->post('/platform/ritual-levels', ['name' => 'Ritualist', 'point_threshold' => 300])->assertRedirect();

        $this->actingAs(User::factory()->create())->get('/platform/ritual-reference')->assertForbidden();
    }

    public function test_impact_change_requires_confirmation_and_stable_key_cannot_change(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $category = RitualCategory::factory()->create();
        $part = RitualPart::factory()->create(['ritual_category_id' => $category->id, 'counts_toward_program' => true, 'point_value' => 10]);
        PersonRitualProficiency::factory()->create(['person_id' => Person::factory()->create()->id, 'ritual_part_id' => $part->id, 'performed_for_credit' => true]);

        $payload = ['key' => $part->key, 'ritual_category_id' => $category->id, 'name' => $part->name, 'description' => null, 'sort_order' => $part->sort_order, 'counts_toward_program' => true, 'point_value' => 20, 'is_active' => true];
        $this->actingAs($admin)->put("/platform/ritual-parts/{$part->id}", $payload)->assertSessionHasErrors('confirm_impact');
        $this->actingAs($admin)->put("/platform/ritual-parts/{$part->id}", $payload + ['confirm_impact' => true])->assertRedirect();
        $this->assertDatabaseHas('ritual_parts', ['id' => $part->id, 'key' => $part->key, 'point_value' => 20]);
        $this->actingAs($admin)->put("/platform/ritual-parts/{$part->id}", array_merge($payload, ['key' => 'changed', 'confirm_impact' => true]))->assertSessionHasErrors('key');
    }
}
