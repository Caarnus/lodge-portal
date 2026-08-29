<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonRitualAvailability;
use App\Models\PersonRitualLevelAchievement;
use App\Models\PersonRitualProficiency;
use App\Models\RitualCategory;
use App\Models\RitualPart;
use App\Models\RitualProgramLevel;
use App\Services\PersonMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RitualMergeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_merge_consolidates_ritual_data_without_broadening_visibility(): void
    {
        $source = Person::factory()->create();
        $survivor = Person::factory()->create();
        $source->ritualSetting()->update(['visibility_scope' => 'participating_lodges', 'public_availability_note' => 'Source note']);
        $survivor->ritualSetting()->update(['visibility_scope' => 'own_lodge', 'public_availability_note' => 'Survivor note']);
        $category = RitualCategory::factory()->create();
        $part = RitualPart::factory()->create(['ritual_category_id' => $category->id]);
        $otherPart = RitualPart::factory()->create(['ritual_category_id' => $category->id]);
        PersonRitualProficiency::factory()->create(['person_id' => $source->id, 'ritual_part_id' => $part->id, 'status' => 'learning', 'interested_in_learning' => true]);
        PersonRitualProficiency::factory()->create(['person_id' => $survivor->id, 'ritual_part_id' => $part->id, 'status' => 'proficient', 'willing_to_assist' => true]);
        PersonRitualProficiency::factory()->create(['person_id' => $source->id, 'ritual_part_id' => $otherPart->id, 'status' => 'proficient', 'willing_to_assist' => true]);
        PersonRitualAvailability::factory()->create(['person_id' => $source->id, 'day_of_week' => 2, 'daypart' => 'evening']);
        PersonRitualAvailability::factory()->create(['person_id' => $survivor->id, 'day_of_week' => 2, 'daypart' => 'evening', 'is_enabled' => false]);
        $level = RitualProgramLevel::factory()->create();
        PersonRitualLevelAchievement::factory()->create(['person_id' => $source->id, 'ritual_program_level_id' => $level->id, 'achieved_at' => now()->subDay()]);
        PersonRitualLevelAchievement::factory()->create(['person_id' => $survivor->id, 'ritual_program_level_id' => $level->id, 'achieved_at' => now()]);

        app(PersonMergeService::class)->merge($source, $survivor);

        $this->assertSame('own_lodge', $survivor->fresh()->ritualSetting->visibility_scope->value);
        $this->assertDatabaseCount('person_ritual_proficiencies', 2);
        $this->assertDatabaseHas('person_ritual_proficiencies', ['person_id' => $survivor->id, 'ritual_part_id' => $part->id, 'status' => 'proficient', 'willing_to_assist' => true]);
        $this->assertDatabaseHas('person_ritual_availabilities', ['person_id' => $survivor->id, 'day_of_week' => 2, 'daypart' => 'evening', 'is_enabled' => true]);
        $this->assertDatabaseCount('person_ritual_level_achievements', 1);
        $this->assertSoftDeleted('people', ['id' => $source->id]);
    }

    public function test_merge_stops_for_conflicting_private_ritual_notes(): void
    {
        $source = Person::factory()->create();
        $survivor = Person::factory()->create();
        $part = RitualPart::factory()->create(['ritual_category_id' => RitualCategory::factory()->create()->id]);
        PersonRitualProficiency::factory()->create(['person_id' => $source->id, 'ritual_part_id' => $part->id, 'notes' => 'Source private note']);
        PersonRitualProficiency::factory()->create(['person_id' => $survivor->id, 'ritual_part_id' => $part->id, 'notes' => 'Survivor private note']);

        $this->expectException(ValidationException::class);
        app(PersonMergeService::class)->merge($source, $survivor);
    }
}
