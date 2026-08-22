<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfilePhotoStoreRequest;
use App\Services\ProfilePhotoService;
use App\Services\SelfProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfilePhotoController extends Controller
{
    public function store(ProfilePhotoStoreRequest $request, SelfProfileService $profiles, ProfilePhotoService $photos): RedirectResponse
    {
        $photos->store($profiles->personFor($request->user()), $request->file('photo'));

        return to_route('profile.edit');
    }

    public function destroy(Request $request, SelfProfileService $profiles, ProfilePhotoService $photos): RedirectResponse
    {
        $photos->remove($profiles->personFor($request->user()));

        return to_route('profile.edit');
    }

    public function show(Request $request, SelfProfileService $profiles)
    {
        $person = $profiles->personFor($request->user());
        abort_unless($person->profile_photo_status === 'ready' && $person->profile_photo_derivative_path && Storage::disk('local')->exists($person->profile_photo_derivative_path), 404);

        return Storage::disk('local')->response($person->profile_photo_derivative_path, 'profile.jpg', ['Cache-Control' => 'private, no-store']);
    }
}
