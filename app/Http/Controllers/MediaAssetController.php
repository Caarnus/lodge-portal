<?php

namespace App\Http\Controllers;

use App\Domain\Galleries\MediaExposureService;
use App\Enums\MediaProcessingStatus;
use App\Jobs\ProcessMediaAsset;
use App\Models\Lodge;
use App\Models\MediaAsset;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MediaAssetController extends Controller
{
    public function store(Request $request, Lodge $lodge)
    {
        abort_unless($request->user()->hasLodgePermission($lodge, 'website.manage') || $request->user()->hasLodgePermission($lodge, 'galleries.manage'), 403);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:' . config('website.max_upload_kb')],
            'alt_text' => 'required|string|max:500',
        ]);
        $file = $data['file'];
        $mime = $file->getMimeType();
        if (!in_array($mime, config('website.allowed_mime_types'), true)) {
            throw ValidationException::withMessages(['file' => 'Upload a JPEG, PNG, WebP, HEIC, or HEIF image.']);
        }

        $path = $file->store('website-originals/' . $lodge->id, 'local');
        $asset = MediaAsset::create([
            'lodge_id' => $lodge->id,
            'uploaded_by' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'original_path' => $path,
            'mime_type' => $mime,
            'size' => $file->getSize(),
            'alt_text' => $data['alt_text'],
            'processing_status' => MediaProcessingStatus::Pending,
        ]);
        Audit::record('website.media_uploaded', $asset, $lodge, null, $asset->toArray());
        ProcessMediaAsset::dispatch($lodge->id, $asset->id);

        return back();
    }

    public function retry(Lodge $lodge, MediaAsset $media)
    {
        $this->allowAsset($lodge, $media);
        $media->update(['processing_status' => MediaProcessingStatus::Pending, 'processing_error' => null]);
        ProcessMediaAsset::dispatch($lodge->id, $media->id);

        return back();
    }

    private function allowAsset(Lodge $lodge, MediaAsset $media): void
    {
        abort_unless($media->lodge_id === $lodge->id, 404);
        abort_unless(request()->user()->hasLodgePermission($lodge, 'website.manage') || request()->user()->hasLodgePermission($lodge, 'galleries.manage'), 403);
    }

    public function update(Request $request, Lodge $lodge, MediaAsset $media)
    {
        $this->allowAsset($lodge, $media);
        $data = $request->validate(['alt_text' => 'required|string|max:500']);
        $before = $media->only('alt_text');
        $media->update($data);
        Audit::record('website.media_updated', $media, $lodge, $before, $media->only('alt_text'));

        return back();
    }

    public function destroy(Lodge $lodge, MediaAsset $media, MediaExposureService $exposure)
    {
        $this->allowAsset($lodge, $media);
        if ($exposure->hasAnyReferences($media)) {
            throw ValidationException::withMessages(['media' => 'Media used by current content cannot be deleted.']);
        }
        if (in_array($media->derivative_path, [$lodge->logo_path, $lodge->seal_path], true)) {
            throw ValidationException::withMessages(['media' => 'Media used by lodge branding cannot be deleted.']);
        }
        $media->delete();
        Audit::record('website.media_deleted', $media, $lodge);

        return back();
    }

    public function original(Lodge $lodge, MediaAsset $media)
    {
        $this->allowAsset($lodge, $media);
        abort_unless(Storage::disk('local')->exists($media->original_path), 404);

        return Storage::disk('local')->download($media->original_path, $media->original_name);
    }
}
