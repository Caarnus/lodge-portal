<?php

namespace Database\Seeders;

use App\Models\MasonicDegree;
use App\Models\RitualCategory;
use App\Models\RitualPart;
use App\Models\RitualProgramLevel;
use Illuminate\Database\Seeder;

class RitualReferenceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->categories() as $category) {
            RitualCategory::query()->firstOrCreate(
                ['key' => $category['key']],
                [
                    'name' => $category['name'],
                    'masonic_degree_id' => $category['degree_key']
                        ? MasonicDegree::query()->where('key', $category['degree_key'])->sole()->id
                        : null,
                    'sort_order' => $category['sort_order'],
                    'is_active' => true,
                ],
            );
        }

        foreach ($this->parts() as $part) {
            RitualPart::query()->firstOrCreate(
                ['key' => $part['key']],
                [
                    'ritual_category_id' => RitualCategory::query()->where('key', $part['category_key'])->sole()->id,
                    'name' => $part['name'],
                    'sort_order' => $part['sort_order'],
                    'counts_toward_program' => true,
                    'point_value' => $part['point_value'],
                    'is_active' => true,
                ],
            );
        }

        foreach ([
            ['key' => 'ritualist', 'name' => 'Ritualist', 'point_threshold' => 300],
            ['key' => 'senior_ritualist', 'name' => 'Senior Ritualist', 'point_threshold' => 700],
            ['key' => 'master_ritualist', 'name' => 'Master Ritualist', 'point_threshold' => 1400],
        ] as $sortOrder => $level) {
            RitualProgramLevel::query()->firstOrCreate(
                ['key' => $level['key']],
                $level + ['sort_order' => $sortOrder * 10, 'is_active' => true],
            );
        }
    }

    private function categories(): array
    {
        return [
            ['key' => 'entered_apprentice', 'name' => 'Entered Apprentice', 'degree_key' => 'entered_apprentice', 'sort_order' => 0],
            ['key' => 'fellow_craft', 'name' => 'Fellow Craft', 'degree_key' => 'fellow_craft', 'sort_order' => 10],
            ['key' => 'master_mason', 'name' => 'Master Mason', 'degree_key' => 'master_mason', 'sort_order' => 20],
            ['key' => 'optional', 'name' => 'Optional and Special Work', 'degree_key' => null, 'sort_order' => 30],
        ];
    }

    private function parts(): array
    {
        return [
            ['key' => 'ea_opened_lodge', 'name' => 'Opened Lodge on EA Degree', 'point_value' => 15, 'category_key' => 'entered_apprentice', 'sort_order' => 0],
            ['key' => 'ea_worshipful_master_first_section', 'name' => 'Worshipful Master 1st Section', 'point_value' => 90, 'category_key' => 'entered_apprentice', 'sort_order' => 10],
            ['key' => 'ea_charge', 'name' => 'E.A. Charge', 'point_value' => 30, 'category_key' => 'entered_apprentice', 'sort_order' => 20],
            ['key' => 'ea_memory_lecture_initial', 'name' => 'E.A. Memory Lecture Initial', 'point_value' => 85, 'category_key' => 'entered_apprentice', 'sort_order' => 30],
            ['key' => 'ea_lecture_second_section', 'name' => 'E.A. Lecture 2nd Section (slide #1)', 'point_value' => 50, 'category_key' => 'entered_apprentice', 'sort_order' => 40],
            ['key' => 'ea_lecture_third_section', 'name' => 'E.A. Lecture 3rd Section (slide #2)', 'point_value' => 70, 'category_key' => 'entered_apprentice', 'sort_order' => 50],
            ['key' => 'fc_opened_lodge', 'name' => 'Opened Lodge on FC Degree', 'point_value' => 15, 'category_key' => 'fellow_craft', 'sort_order' => 0],
            ['key' => 'fc_worshipful_master_first_section', 'name' => '1st Section Worshipful Master', 'point_value' => 90, 'category_key' => 'fellow_craft', 'sort_order' => 10],
            ['key' => 'fc_middle_chamber_lecture_traditional', 'name' => 'F.C. Middle Chamber Lecture Traditional', 'point_value' => 225, 'category_key' => 'fellow_craft', 'sort_order' => 20],
            ['key' => 'fc_middle_chamber_lecture_abbreviated', 'name' => 'F.C. Middle Chamber Lecture Abbreviated Version', 'point_value' => 135, 'category_key' => 'fellow_craft', 'sort_order' => 30],
            ['key' => 'fc_letter_g_lecture', 'name' => "F.C. Letter 'G' Lecture", 'point_value' => 30, 'category_key' => 'fellow_craft', 'sort_order' => 40],
            ['key' => 'fc_memory_lecture_initial', 'name' => 'F.C. Memory Lecture Initial', 'point_value' => 85, 'category_key' => 'fellow_craft', 'sort_order' => 50],
            ['key' => 'fc_charge', 'name' => 'F.C. Charge', 'point_value' => 30, 'category_key' => 'fellow_craft', 'sort_order' => 60],
            ['key' => 'mm_opened_lodge', 'name' => 'Opened Lodge on MM Degree', 'point_value' => 15, 'category_key' => 'master_mason', 'sort_order' => 0],
            ['key' => 'mm_worshipful_master_first_section', 'name' => 'Worshipful Master 1st Section', 'point_value' => 90, 'category_key' => 'master_mason', 'sort_order' => 10],
            ['key' => 'mm_charge', 'name' => 'M.M. Charge', 'point_value' => 30, 'category_key' => 'master_mason', 'sort_order' => 20],
            ['key' => 'mm_memory_lecture_initial', 'name' => 'M.M. Memory Lecture Initial', 'point_value' => 85, 'category_key' => 'master_mason', 'sort_order' => 30],
            ['key' => 'mm_lecture_second_section', 'name' => 'M.M. 2nd Section Lecture (slide #1)', 'point_value' => 60, 'category_key' => 'master_mason', 'sort_order' => 40],
            ['key' => 'mm_lecture_third_section', 'name' => 'M.M. 3rd Section Lecture (slide #2)', 'point_value' => 60, 'category_key' => 'master_mason', 'sort_order' => 50],
            ['key' => 'mm_senior_deacon_third_r', 'name' => 'Senior Deacon (Conducts candidate to 3rd R)', 'point_value' => 15, 'category_key' => 'master_mason', 'sort_order' => 60],
            ['key' => 'mm_first_ruffian', 'name' => '1st Ruffian', 'point_value' => 30, 'category_key' => 'master_mason', 'sort_order' => 70],
            ['key' => 'mm_second_ruffian', 'name' => '2nd Ruffian', 'point_value' => 30, 'category_key' => 'master_mason', 'sort_order' => 80],
            ['key' => 'mm_third_ruffian', 'name' => '3rd Ruffian', 'point_value' => 15, 'category_key' => 'master_mason', 'sort_order' => 90],
            ['key' => 'mm_sea_captain', 'name' => 'Sea Captain', 'point_value' => 10, 'category_key' => 'master_mason', 'sort_order' => 100],
            ['key' => 'mm_first_fellow_craft', 'name' => '1st Fellow Craft', 'point_value' => 75, 'category_key' => 'master_mason', 'sort_order' => 110],
            ['key' => 'mm_second_fellow_craft', 'name' => '2nd Fellow Craft', 'point_value' => 30, 'category_key' => 'master_mason', 'sort_order' => 120],
            ['key' => 'mm_third_fellow_craft', 'name' => '3rd Fellow Craft', 'point_value' => 20, 'category_key' => 'master_mason', 'sort_order' => 130],
            ['key' => 'mm_fourth_fellow_craft', 'name' => '4th Fellow Craft', 'point_value' => 10, 'category_key' => 'master_mason', 'sort_order' => 140],
            ['key' => 'mm_fifth_fellow_craft', 'name' => '5th Fellow Craft', 'point_value' => 10, 'category_key' => 'master_mason', 'sort_order' => 150],
            ['key' => 'mm_sixth_fellow_craft', 'name' => '6th Fellow Craft', 'point_value' => 10, 'category_key' => 'master_mason', 'sort_order' => 160],
            ['key' => 'mm_hiram_king_of_tyre', 'name' => 'Hiram King of Tyre', 'point_value' => 20, 'category_key' => 'master_mason', 'sort_order' => 170],
            ['key' => 'mm_king_solomon', 'name' => 'King Solomon', 'point_value' => 30, 'category_key' => 'master_mason', 'sort_order' => 180],
            ['key' => 'mm_wayfaring_man', 'name' => 'Wayfaring Man', 'point_value' => 10, 'category_key' => 'master_mason', 'sort_order' => 190],
            ['key' => 'mm_graveside_prayer', 'name' => 'Graveside Prayer', 'point_value' => 30, 'category_key' => 'master_mason', 'sort_order' => 200],
            ['key' => 'optional_master_mason_bible_presentation', 'name' => 'Master Mason Bible Presentation', 'point_value' => 60, 'category_key' => 'optional', 'sort_order' => 0],
            ['key' => 'optional_ea_apron_lecture', 'name' => 'E.A. Apron Lecture', 'point_value' => 30, 'category_key' => 'optional', 'sort_order' => 10],
            ['key' => 'optional_third_ruffian_soliloquy', 'name' => '3rd Ruffian Soliloquy', 'point_value' => 30, 'category_key' => 'optional', 'sort_order' => 20],
            ['key' => 'optional_mm_charge_yonder_book', 'name' => 'M.M. Optional Charge (Yonder Book)', 'point_value' => 45, 'category_key' => 'optional', 'sort_order' => 30],
            ['key' => 'optional_memorial_service', 'name' => 'Memorial Service', 'point_value' => 90, 'category_key' => 'optional', 'sort_order' => 40],
            ['key' => 'optional_past_masters_degree_initial', 'name' => 'Past Masters Degree Initial', 'point_value' => 90, 'category_key' => 'optional', 'sort_order' => 50],
            ['key' => 'optional_grand_lodge_vault_ritual_review', 'name' => 'Grand Lodge Vault Ritual Review', 'point_value' => 20, 'category_key' => 'optional', 'sort_order' => 60],
        ];
    }
}
