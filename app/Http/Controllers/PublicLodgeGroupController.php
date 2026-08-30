<?php

namespace App\Http\Controllers;

use App\Models\LodgeGroup;
use App\Services\PublicLodgeDirectory;
use Inertia\Inertia;

class PublicLodgeGroupController extends Controller
{
    public function show(string $slug, PublicLodgeDirectory $directory)
    {
        $group = $directory->publicGroup($slug);
        $lodgeIds = $group->lodges()->where('status', 'active')->pluck('lodges.id')->all();

        return Inertia::render('public/LodgeGroup', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
                'description' => $group->description,
                'type' => $group->type->name,
            ],
            'lodges' => $directory->cardsFor($lodgeIds),
            'events' => $directory->upcomingPublicEvents($group),
        ]);
    }
}
