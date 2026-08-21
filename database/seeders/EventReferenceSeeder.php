<?php

namespace Database\Seeders;

use App\Models\EventCategory;
use Illuminate\Database\Seeder;

class EventReferenceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Stated Meeting',
            'Degree',
            'Practice',
            'Education',
            'Fellowship',
            'Community Service',
            'Fundraiser',
            'Other',
        ] as $sortOrder => $name) {
            EventCategory::updateOrCreate(
                ['key' => str($name)->slug()->toString()],
                ['name' => $name, 'sort_order' => $sortOrder * 10, 'is_active' => true],
            );
        }
    }
}
