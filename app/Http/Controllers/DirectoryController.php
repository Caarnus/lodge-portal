<?php

namespace App\Http\Controllers;

use App\Domain\Directory\DirectoryAccess;
use App\Enums\DirectoryAudience;
use App\Http\Requests\DirectoryIndexRequest;
use App\Models\Lodge;
use App\Models\MasonicDegree;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DirectoryController extends Controller
{
    public function index(DirectoryIndexRequest $request, Lodge $lodge, DirectoryAccess $directory)
    {
        abort_unless($directory->canBrowse($request->user(), $lodge), 403);
        $audience = DirectoryAudience::tryFrom($request->input('audience')) ?? DirectoryAudience::OwnLodge;
        $people = $directory->search(
            $lodge,
            $audience,
            $request->input('query'),
            $request->integer('degree') ?: null,
        )->withQueryString();

        return Inertia::render('directory/Index', [
            'lodge' => $lodge->only(['id', 'name', 'number']),
            'people' => $people,
            'filters' => [
                'audience' => $audience->value,
                'query' => $request->input('query', ''),
                'degree' => $request->integer('degree') ?: null,
            ],
            'degrees' => MasonicDegree::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, Lodge $lodge, Person $person, DirectoryAccess $directory)
    {
        abort_unless($directory->canBrowse($request->user(), $lodge), 403);
        $audience = DirectoryAudience::tryFrom($request->query('audience')) ?? DirectoryAudience::OwnLodge;
        $visible = $directory->findVisible($lodge, $person, $audience);
        abort_unless($visible, 404);

        return Inertia::render('directory/Show', [
            'lodge' => $lodge->only(['id', 'name', 'number']),
            'audience' => $audience->value,
            'person' => $directory->project($visible, $lodge, $audience),
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    public function photo(Request $request, Lodge $lodge, Person $person, DirectoryAccess $directory)
    {
        abort_unless($directory->canBrowse($request->user(), $lodge), 403);
        $audience = DirectoryAudience::tryFrom($request->query('audience')) ?? DirectoryAudience::OwnLodge;
        $visible = $directory->findVisible($lodge, $person, $audience);
        abort_unless(
            $visible && $visible->directoryPrivacySetting?->show_profile_photo
            && $visible->profile_photo_status === 'ready'
            && $visible->profile_photo_derivative_path
            && Storage::disk('local')->exists($visible->profile_photo_derivative_path),
            404,
        );

        return Storage::disk('local')->response($visible->profile_photo_derivative_path, 'profile.jpg', ['Cache-Control' => 'private, no-store']);
    }
}
