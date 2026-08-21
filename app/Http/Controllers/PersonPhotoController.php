<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessProfilePhoto;
use App\Models\Lodge;
use App\Models\Person;
use App\Services\Audit;
use App\Services\PersonAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PersonPhotoController extends Controller
{
    public function store(Request $request, Lodge $lodge, Person $person, PersonAccess $access)
    {
        abort_unless($access->canManagePerson($request->user(), $lodge, $person), 403);
        $data = $request->validate(['photo' => ['required', 'file', 'max:'.config('website.max_upload_kb')]]);
        $file = $data['photo'];
        if (! in_array($file->getMimeType(), config('website.allowed_mime_types'), true)) {
            throw ValidationException::withMessages(['photo' => 'Upload a JPEG, PNG, WebP, HEIC, or HEIF image.']);
        }
        $path = $file->store('profile-originals/'.$person->id, 'local');
        if (! $path) {
            throw ValidationException::withMessages(['photo' => 'The profile photo could not be stored.']);
        }
        if ($person->profile_photo_path) {
            Storage::disk('local')->delete($person->profile_photo_path);
        }
        $person->update(['profile_photo_path' => $path, 'profile_photo_original_name' => $file->getClientOriginalName(), 'profile_photo_status' => 'pending', 'profile_photo_error' => null]);
        Audit::record('person.photo_uploaded', $person, $lodge, null, ['profile_photo_status' => 'pending']);
        ProcessProfilePhoto::dispatch($person->id, $path);

        return back();
    }

    public function show(Request $request, Lodge $lodge, Person $person, PersonAccess $access)
    {
        abort_unless($access->canView($request->user(), $lodge, $person), 404);
        abort_unless($person->profile_photo_derivative_path && Storage::disk('local')->exists($person->profile_photo_derivative_path), 404);

        return Storage::disk('local')->response($person->profile_photo_derivative_path, 'profile.jpg', ['Cache-Control' => 'private, max-age=3600']);
    }
}
