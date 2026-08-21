<?php

namespace Database\Seeders;

use App\Models\Lodge;
use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (['lodge.manage' => 'Manage lodge identity and settings', 'registration.review' => 'Review registrations', 'website.manage' => 'Manage lodge website', 'website.publish' => 'Publish lodge website'] as $key => $name) {
            Permission::firstOrCreate(['key' => $key], ['name' => $name]);
        }

        foreach ([
            ['name' => 'Lodge A', 'number' => '101', 'slug' => 'lodge-a', 'city' => 'Evansville'],
            ['name' => 'Lodge B', 'number' => '202', 'slug' => 'lodge-b', 'city' => 'Newburgh'],
        ] as $lodge) {
            Lodge::firstOrCreate(['slug' => $lodge['slug']], $lodge + [
                'state' => 'IN',
                'jurisdiction' => 'Indiana',
                'physical_address' => '100 Test Street',
                'timezone' => 'America/Chicago',
                'public_email' => $lodge['slug'].'@example.test',
                'status' => 'active',
                'primary_color' => '#1E3A5F',
                'secondary_color' => '#D4AF37',
            ]);
        }
    }
}
