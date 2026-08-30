<?php

namespace Database\Seeders;

use App\Models\LodgeGroupType;
use Illuminate\Database\Seeder;

class LodgeGroupTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['key' => 'region', 'name' => 'Region'],
            ['key' => 'district', 'name' => 'District'],
            ['key' => 'county', 'name' => 'County'],
            ['key' => 'informal', 'name' => 'Informal'],
            ['key' => 'other', 'name' => 'Other'],
        ] as $sortOrder => $type) {
            LodgeGroupType::query()->updateOrCreate(['key' => $type['key']], $type + [
                'sort_order' => $sortOrder * 10,
                'is_active' => true,
            ]);
        }
    }
}
