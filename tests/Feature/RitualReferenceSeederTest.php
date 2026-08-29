<?php

namespace Tests\Feature;

use App\Enums\RitualDaypart;
use App\Enums\RitualProficiencyStatus;
use App\Enums\RitualVisibilityScope;
use App\Models\Lodge;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonRitualAvailability;
use App\Models\PersonRitualProficiency;
use App\Models\PersonRitualSetting;
use App\Models\RitualCategory;
use App\Models\RitualPart;
use App\Models\RitualProgramLevel;
use App\Models\Role;
use App\Services\LodgeRoleCatalog;
use Database\Seeders\PeopleMembershipReferenceSeeder;
use Database\Seeders\RitualReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RitualReferenceSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeopleMembershipReferenceSeeder::class);
        $this->seed(RitualReferenceSeeder::class);
    }

    public function test_seeded_catalog_has_exact_labels_groups_values_and_totals(): void
    {
        $expected = [
            'entered_apprentice' => [
                'Opened Lodge on EA Degree' => 15,
                'Worshipful Master 1st Section' => 90,
                'E.A. Charge' => 30,
                'E.A. Memory Lecture Initial' => 85,
                'E.A. Lecture 2nd Section (slide #1)' => 50,
                'E.A. Lecture 3rd Section (slide #2)' => 70,
            ],
            'fellow_craft' => [
                'Opened Lodge on FC Degree' => 15,
                '1st Section Worshipful Master' => 90,
                'F.C. Middle Chamber Lecture Traditional' => 225,
                'F.C. Middle Chamber Lecture Abbreviated Version' => 135,
                "F.C. Letter 'G' Lecture" => 30,
                'F.C. Memory Lecture Initial' => 85,
                'F.C. Charge' => 30,
            ],
            'master_mason' => [
                'Opened Lodge on MM Degree' => 15,
                'Worshipful Master 1st Section' => 90,
                'M.M. Charge' => 30,
                'M.M. Memory Lecture Initial' => 85,
                'M.M. 2nd Section Lecture (slide #1)' => 60,
                'M.M. 3rd Section Lecture (slide #2)' => 60,
                'Senior Deacon (Conducts candidate to 3rd R)' => 15,
                '1st Ruffian' => 30,
                '2nd Ruffian' => 30,
                '3rd Ruffian' => 15,
                'Sea Captain' => 10,
                '1st Fellow Craft' => 75,
                '2nd Fellow Craft' => 30,
                '3rd Fellow Craft' => 20,
                '4th Fellow Craft' => 10,
                '5th Fellow Craft' => 10,
                '6th Fellow Craft' => 10,
                'Hiram King of Tyre' => 20,
                'King Solomon' => 30,
                'Wayfaring Man' => 10,
                'Graveside Prayer' => 30,
            ],
            'optional' => [
                'Master Mason Bible Presentation' => 60,
                'E.A. Apron Lecture' => 30,
                '3rd Ruffian Soliloquy' => 30,
                'M.M. Optional Charge (Yonder Book)' => 45,
                'Memorial Service' => 90,
                'Past Masters Degree Initial' => 90,
                'Grand Lodge Vault Ritual Review' => 20,
            ],
        ];

        $this->assertSame(4, RitualCategory::query()->count());
        $this->assertSame(41, RitualPart::query()->count());
        $this->assertSame(2000, RitualPart::query()->sum('point_value'));

        foreach ($expected as $categoryKey => $parts) {
            $category = RitualCategory::query()->where('key', $categoryKey)->sole();
            $actual = $category->parts()->orderBy('sort_order')->pluck('point_value', 'name')->all();

            $this->assertSame($parts, $actual);
            $this->assertSame(array_sum($parts), array_sum($actual));
        }

        $this->assertSame([
            'ritualist' => 300,
            'senior_ritualist' => 700,
            'master_ritualist' => 1400,
        ], RitualProgramLevel::query()->orderBy('sort_order')->pluck('point_threshold', 'key')->all());
    }

    public function test_seed_rerun_does_not_overwrite_administrator_changes(): void
    {
        RitualPart::query()->where('key', 'ea_charge')->sole()->update(['point_value' => 31, 'name' => 'Administrator label']);
        RitualCategory::query()->where('key', 'optional')->sole()->update(['name' => 'Administrator category']);
        RitualProgramLevel::query()->where('key', 'ritualist')->sole()->update(['point_threshold' => 301]);

        $this->seed(RitualReferenceSeeder::class);

        $this->assertSame(31, RitualPart::query()->where('key', 'ea_charge')->sole()->point_value);
        $this->assertSame('Administrator label', RitualPart::query()->where('key', 'ea_charge')->sole()->name);
        $this->assertSame('Administrator category', RitualCategory::query()->where('key', 'optional')->sole()->name);
        $this->assertSame(301, RitualProgramLevel::query()->where('key', 'ritualist')->sole()->point_threshold);
    }

    public function test_ritual_models_cast_enums_and_person_relationships(): void
    {
        $setting = PersonRitualSetting::factory()->create();
        $proficiency = PersonRitualProficiency::factory()->create(['person_id' => $setting->person_id]);
        $availability = PersonRitualAvailability::factory()->create(['person_id' => $setting->person_id]);

        $this->assertSame(RitualVisibilityScope::Hidden, $setting->visibility_scope);
        $this->assertSame(RitualProficiencyStatus::NotKnown, $proficiency->status);
        $this->assertContains($availability->daypart, RitualDaypart::cases());
        $this->assertTrue($setting->person->ritualSetting->is($setting));
        $this->assertTrue($setting->person->ritualProficiencies->contains($proficiency));
        $this->assertTrue($setting->person->ritualAvailabilities->contains($availability));
    }

    public function test_creating_a_person_materializes_hidden_ritual_settings(): void
    {
        $person = Person::factory()->create();

        $this->assertSame(RitualVisibilityScope::Hidden, $person->ritualSetting->visibility_scope);
    }

    public function test_role_catalog_grants_built_in_roles_without_changing_custom_roles(): void
    {
        $lodge = Lodge::factory()->create();
        $catalog = app(LodgeRoleCatalog::class);
        $catalog->ensureFor($lodge);
        $custom = Role::query()->create(['lodge_id' => $lodge->id, 'name' => 'Custom', 'is_system' => false]);
        $directoryPermission = Permission::query()->where('key', 'directory.view')->sole();
        $custom->permissions()->attach($directoryPermission);

        $catalog->ensureFor($lodge);

        foreach (['Administrator', 'Officer', 'Member'] as $roleName) {
            $this->assertTrue(Role::query()->where('lodge_id', $lodge->id)->where('name', $roleName)->sole()
                ->permissions()->where('key', 'ritual.search')->exists());
        }
        $this->assertFalse(Role::query()->where('lodge_id', $lodge->id)->where('name', 'Non-member')->sole()
            ->permissions()->where('key', 'ritual.search')->exists());
        $this->assertTrue($custom->fresh()->permissions()->whereKey($directoryPermission->id)->exists());
        $this->assertFalse($custom->fresh()->permissions()->where('key', 'ritual.search')->exists());
    }
}
