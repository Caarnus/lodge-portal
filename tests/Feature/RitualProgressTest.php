<?php

namespace Tests\Feature;

use App\Domain\Ritual\RitualAchievementService;
use App\Domain\Ritual\RitualProgress;
use App\Enums\RitualProficiencyStatus;
use App\Models\Person;
use App\Models\PersonRitualLevelAchievement;
use App\Models\PersonRitualProficiency;
use App\Models\RitualPart;
use Database\Seeders\PeopleMembershipReferenceSeeder;
use Database\Seeders\RitualReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RitualProgressTest extends TestCase
{
    use RefreshDatabase;

    private RitualProgress $progress;

    private RitualAchievementService $achievements;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeopleMembershipReferenceSeeder::class);
        $this->seed(RitualReferenceSeeder::class);
        $this->progress = app(RitualProgress::class);
        $this->achievements = app(RitualAchievementService::class);
    }

    public function test_proficient_without_credit_earns_no_points_and_credit_requires_confirmation(): void
    {
        $person = Person::factory()->create();
        $part = $this->part('ea_worshipful_master_first_section');

        $this->progress->updateProficiency($person, $part, [
            'status' => RitualProficiencyStatus::Proficient,
            'willing_to_assist' => true,
        ]);
        $this->assertSame(0, $this->progress->currentTotal($person));

        try {
            $this->progress->updateProficiency($person, $part, [
                'performed_for_credit' => true,
            ]);
            $this->fail('Expected credit confirmation validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('confirm_performed_for_credit', $exception->errors());
        }

        $this->progress->updateProficiency($person, $part, [
            'performed_for_credit' => true,
            'confirm_performed_for_credit' => true,
        ]);

        $this->assertSame(90, $this->progress->currentTotal($person));
    }

    public function test_credit_remains_after_proficiency_downgrade_but_willingness_is_cleared(): void
    {
        $person = Person::factory()->create();
        $part = $this->part('ea_worshipful_master_first_section');
        $this->progress->updateProficiency($person, $part, [
            'status' => RitualProficiencyStatus::Proficient,
            'willing_to_assist' => true,
            'performed_for_credit' => true,
            'confirm_performed_for_credit' => true,
        ]);

        $proficiency = $this->progress->updateProficiency($person, $part, [
            'status' => RitualProficiencyStatus::Learning,
        ]);

        $this->assertSame(RitualProficiencyStatus::Learning, $proficiency->status);
        $this->assertFalse($proficiency->willing_to_assist);
        $this->assertTrue($proficiency->performed_for_credit);
        $this->assertSame(90, $this->progress->currentTotal($person));
    }

    public function test_active_point_rules_and_current_values_control_totals(): void
    {
        $person = Person::factory()->create();
        $pointPart = $this->part('ea_charge');
        $nonPoint = RitualPart::factory()->nonPoint()->create();
        $inactive = $this->part('fc_charge');
        $inactive->update(['is_active' => false]);

        $this->credit($person, $pointPart);
        $this->credit($person, $nonPoint);
        $this->credit($person, $inactive);
        $this->assertSame(30, $this->progress->currentTotal($person));

        $pointPart->update(['point_value' => 42]);
        $this->assertSame(42, $this->progress->currentTotal($person));
        $this->assertCount(1, $this->progress->creditedActiveParts($person));
        $this->assertCount(1, $this->progress->creditedRetiredParts($person));
    }

    public function test_thresholds_are_created_once_and_remain_after_credit_correction(): void
    {
        $person = Person::factory()->create();
        foreach ([
            'fc_middle_chamber_lecture_traditional',
            'fc_middle_chamber_lecture_abbreviated',
            'ea_worshipful_master_first_section',
            'mm_worshipful_master_first_section',
            'mm_memory_lecture_initial',
            'optional_memorial_service',
            'optional_past_masters_degree_initial',
            'mm_first_fellow_craft',
            'mm_lecture_second_section',
            'mm_lecture_third_section',
            'mm_king_solomon',
            'mm_charge',
            'mm_first_ruffian',
            'mm_second_ruffian',
            'mm_graveside_prayer',
            'fc_memory_lecture_initial',
            'ea_memory_lecture_initial',
            'ea_lecture_third_section',
            'fc_letter_g_lecture',
            'ea_lecture_second_section',
            'optional_master_mason_bible_presentation',
        ] as $key) {
            $this->credit($person, $this->part($key));
        }

        $this->assertSame(1530, $this->progress->currentTotal($person));
        $this->achievements->reconcile($person);
        $this->achievements->reconcile($person);
        $this->assertSame(3, PersonRitualLevelAchievement::query()->where('person_id', $person->id)->count());

        PersonRitualProficiency::query()->where('person_id', $person->id)->update(['performed_for_credit' => false]);
        $projection = $this->progress->projection($person);

        $this->assertSame(0, $projection['current_total']);
        $this->assertSame(3, $projection['achievements']->count());
        $this->assertTrue($projection['current_total_below_highest_achievement']);
    }

    public function test_reference_part_changes_reconcile_only_people_with_credit_for_that_part(): void
    {
        $part = $this->part('ea_charge');
        $affected = Person::factory()->create();
        $unaffected = Person::factory()->create();
        $this->credit($affected, $part);
        $this->credit($unaffected, $this->part('fc_charge'));
        $part->update(['point_value' => 300]);

        $this->assertSame(1, $this->achievements->reconcilePart($part));
        $this->assertDatabaseHas('person_ritual_level_achievements', [
            'person_id' => $affected->id,
            'level_name_snapshot' => 'Ritualist',
        ]);
        $this->assertDatabaseMissing('person_ritual_level_achievements', ['person_id' => $unaffected->id]);
    }

    public function test_category_reactivation_and_level_threshold_reduction_reconcile_credited_people(): void
    {
        $person = Person::factory()->create();
        $part = $this->part('ea_charge');
        $this->credit($person, $part);
        $part->update(['point_value' => 300]);
        $part->category->update(['is_active' => false]);

        $this->assertSame(0, $this->progress->currentTotal($person));
        $part->category->update(['is_active' => true]);
        $this->assertSame(1, $this->achievements->reconcileCategory($part->category));
        $this->assertDatabaseHas('person_ritual_level_achievements', [
            'person_id' => $person->id,
            'level_name_snapshot' => 'Ritualist',
        ]);

        $other = Person::factory()->create();
        $this->credit($other, $this->part('fc_charge'));
        $level = \App\Models\RitualProgramLevel::query()->where('key', 'senior_ritualist')->sole();
        $level->update(['point_threshold' => 30]);

        $this->assertSame(2, $this->achievements->reconcileLevels());
        $this->assertDatabaseHas('person_ritual_level_achievements', [
            'person_id' => $other->id,
            'level_name_snapshot' => 'Senior Ritualist',
        ]);
    }

    private function part(string $key): RitualPart
    {
        return RitualPart::query()->where('key', $key)->sole();
    }

    private function credit(Person $person, RitualPart $part): void
    {
        PersonRitualProficiency::query()->create([
            'person_id' => $person->id,
            'ritual_part_id' => $part->id,
            'status' => RitualProficiencyStatus::Proficient,
            'performed_for_credit' => true,
        ]);
    }
}
