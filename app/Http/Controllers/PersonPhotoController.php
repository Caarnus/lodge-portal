<?php

namespace App\Http\Controllers;

use App\Models\Lodge;
use App\Models\Person;
use App\Services\PersonAccess;
use App\Services\ProfilePhotoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PersonPhotoController extends Controller
{
    public function store(Request $request, Lodge $lodge, Person $person, PersonAccess $access, ProfilePhotoService $photos)
    {
        abort_unless($access->canManagePerson($request->user(), $lodge, $person), 403);
        $data = $request->validate(['photo' => ['required', 'file', 'max:'.config('website.max_upload_kb')]]);
        $photos->store($person, $data['photo'], $lodge);

        return back();
    }

    public function show(Request $request, Lodge $lodge, Person $person, PersonAccess $access)
    {
        abort_unless($access->canView($request->user(), $lodge, $person), 404);
        abort_unless($person->profile_photo_derivative_path && Storage::disk('local')->exists($person->profile_photo_derivative_path), 404);

        return Storage::disk('local')->response($person->profile_photo_derivative_path, 'profile.jpg', ['Cache-Control' => 'private, no-store']);
    }
}
