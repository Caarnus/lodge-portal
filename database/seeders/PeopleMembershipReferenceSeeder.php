<?php

namespace Database\Seeders;

use App\Models\MasonicDegree;
use App\Models\MembershipStatus;
use App\Models\MembershipType;
use App\Models\OfficerPosition;
use App\Models\RelationshipType;
use Closure;
use Illuminate\Database\Seeder;

class PeopleMembershipReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedReferences(fn (array $attributes, array $values) => MembershipType::updateOrCreate($attributes, $values), [
            ['key' => 'initiation', 'name' => 'Initiation'],
            ['key' => 'affiliation', 'name' => 'Affiliation'],
            ['key' => 'dual', 'name' => 'Dual'],
            ['key' => 'honorary', 'name' => 'Honorary'],
        ]);
        $this->seedReferences(fn (array $attributes, array $values) => MembershipStatus::updateOrCreate($attributes, $values), [
            ['key' => 'petitioner', 'name' => 'Petitioner'],
            ['key' => 'active', 'name' => 'Active', 'is_default' => true],
            ['key' => 'demitted', 'name' => 'Demitted'],
            ['key' => 'suspended', 'name' => 'Suspended'],
            ['key' => 'expelled', 'name' => 'Expelled'],
            ['key' => 'deceased', 'name' => 'Deceased'],
        ]);
        $this->seedReferences(fn (array $attributes, array $values) => MasonicDegree::updateOrCreate($attributes, $values), [
            ['key' => 'entered_apprentice', 'name' => 'Entered Apprentice'],
            ['key' => 'fellow_craft', 'name' => 'Fellow Craft'],
            ['key' => 'master_mason', 'name' => 'Master Mason'],
        ]);

        foreach ([
            ['key' => 'spouse', 'name' => 'Spouse', 'inverse_key' => 'spouse', 'inverse_name' => 'Spouse', 'is_symmetric' => true],
            ['key' => 'child', 'name' => 'Child', 'inverse_key' => 'parent', 'inverse_name' => 'Parent'],
            ['key' => 'parent', 'name' => 'Parent', 'inverse_key' => 'child', 'inverse_name' => 'Child'],
            ['key' => 'widow_widower', 'name' => 'Widow/Widower', 'inverse_key' => 'deceased_spouse', 'inverse_name' => 'Deceased spouse'],
            ['key' => 'deceased_spouse', 'name' => 'Deceased spouse', 'inverse_key' => 'widow_widower', 'inverse_name' => 'Widow/Widower', 'is_active' => false],
            ['key' => 'guardian', 'name' => 'Guardian', 'inverse_key' => 'ward', 'inverse_name' => 'Ward'],
            ['key' => 'ward', 'name' => 'Ward', 'inverse_key' => 'guardian', 'inverse_name' => 'Guardian', 'is_active' => false],
        ] as $order => $item) {
            RelationshipType::updateOrCreate(['key' => $item['key']], $item + ['sort_order' => $order * 10, 'is_active' => $item['is_active'] ?? true]);
        }

        foreach ([
            'Worshipful Master', 'Senior Warden', 'Junior Warden', 'Treasurer', 'Secretary', 'Chaplain',
            'Senior Deacon', 'Junior Deacon', 'Senior Steward', 'Junior Steward', 'Marshal', 'Tyler',
        ] as $order => $name) {
            $key = str($name)->snake()->toString();
            OfficerPosition::updateOrCreate(['key' => $key], ['name' => $name, 'sort_order' => $order * 10, 'is_active' => true]);
        }
        OfficerPosition::updateOrCreate(['key' => 'trustee1'], ['name' => 'Trustee', 'sort_order' => 120, 'is_active' => true]);
        OfficerPosition::updateOrCreate(['key' => 'trustee2'], ['name' => 'Trustee', 'sort_order' => 130, 'is_active' => true]);
        OfficerPosition::updateOrCreate(['key' => 'trustee3'], ['name' => 'Trustee', 'sort_order' => 140, 'is_active' => true]);
    }

    private function seedReferences(Closure $upsert, array $items): void
    {
        foreach ($items as $order => $item) {
            $upsert(['key' => $item['key']], $item + [
                'sort_order' => $order * 10,
                'is_active' => true,
                'is_default' => false,
            ]);
        }
    }
}
