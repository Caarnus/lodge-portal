<?php

namespace App\Http\Controllers;

use App\Services\PublicLodgeDirectory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicLodgeDirectoryController extends Controller
{
    public function index(Request $request, PublicLodgeDirectory $directory)
    {
        $filters = $request->validate([
            'group' => ['nullable', 'string', 'max:100'],
            'group_type' => ['nullable', 'string', 'max:100'],
            'query' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return Inertia::render('public/Lodges', [
            'lodges' => $directory->paginate($filters),
            'filters' => $filters,
            'groups' => $directory->publicGroups(),
            'groupTypes' => $directory->publicGroupTypes(),
        ]);
    }
}
