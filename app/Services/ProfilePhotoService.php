<?php

namespace App\Services;

use App\Jobs\ProcessProfilePhoto;
use App\Models\Lodge;
use App\Models\Person;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfilePhotoService
{
    public function store(Person $person, UploadedFile $file, ?Lodge $lodge = null): void
    {
        if (!in_array($file->getMimeType(), config('website.allowed_mime_types'), true)) {
            throw ValidationException::withMessages(['photo' => 'Upload a JPEG, PNG, WebP, HEIC, or HEIF image.']);
        }
        $path = $file->store('profile-originals/' . $person->id, 'local');
        if (!$path) {
            throw ValidationException::withMessages(['photo' => 'The profile photo could not be stored.']);
        }

        if ($person->profile_photo_path) {
            Storage::disk('local')->delete($person->profile_photo_path);
        }
        $person->update([
            'profile_photo_path' => $path,
            'profile_photo_original_name' => $file->getClientOriginalName(),
            'profile_photo_status' => 'pending',
            'profile_photo_error' => null,
        ]);
        Audit::record('person.photo_uploaded', $person, $lodge, null, ['profile_photo_status' => 'pending']);
        ProcessProfilePhoto::dispatch($person->id, $path);
    }

    public function remove(Person $person, ?Lodge $lodge = null): void
    {
        $before = ['profile_photo_status' => $person->profile_photo_status];
        $paths = array_filter([$person->profile_photo_path, $person->profile_photo_derivative_path]);
        $person->update([
            'profile_photo_path' => null,
            'profile_photo_derivative_path' => null,
            'profile_photo_original_name' => null,
            'profile_photo_status' => null,
            'profile_photo_error' => null,
        ]);
        Storage::disk('local')->delete($paths);
        Audit::record('person.photo_removed', $person, $lodge, $before, ['profile_photo_status' => null]);
    }
}
