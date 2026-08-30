<?php

namespace Database\Seeders;

use App\Models\Lodge;
use App\Services\LodgeRoleCatalog;
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
        app(LodgeRoleCatalog::class)->seedPermissions();
        $this->call(PeopleMembershipReferenceSeeder::class);
        $this->call(RitualReferenceSeeder::class);
        $this->call(EventReferenceSeeder::class);
        $this->call(LodgeGroupTypeSeeder::class);

        foreach ([
            ['name' => 'Lodge A', 'number' => '101', 'slug' => 'lodge-a', 'city' => 'Evansville'],
            ['name' => 'Lodge B', 'number' => '202', 'slug' => 'lodge-b', 'city' => 'Newburgh'],
        ] as $lodge) {
            $created = Lodge::firstOrCreate(['slug' => $lodge['slug']], $lodge + [
                'state' => 'IN',
                'jurisdiction' => 'Indiana',
                'physical_address' => '100 Test Street',
                'timezone' => 'America/Chicago',
                'public_email' => $lodge['slug'].'@example.test',
                'status' => 'active',
                'primary_color' => '#1E3A5F',
                'secondary_color' => '#D4AF37',
            ]);
            app(LodgeRoleCatalog::class)->ensureFor($created);
        }
    }
}
