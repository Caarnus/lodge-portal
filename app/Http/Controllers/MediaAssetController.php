<?php

namespace App\Http\Controllers;

use App\Enums\MediaProcessingStatus;
use App\Jobs\ProcessMediaAsset;
use App\Models\Lodge;
use App\Models\MediaAsset;
use App\Models\WebsiteSection;
use App\Services\Audit;
use App\Services\WebsiteSectionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MediaAssetController extends Controller
{
    public function store(Request $request, Lodge $lodge)
    {
        $this->allowLodge($lodge, 'website.manage');
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.config('website.max_upload_kb')],
            'alt_text' => 'required|string|max:500',
        ]);
        $file = $data['file'];
        $mime = $file->getMimeType();
        if (! in_array($mime, config('website.allowed_mime_types'), true)) {
            throw ValidationException::withMessages(['file' => 'Upload a JPEG, PNG, WebP, HEIC, or HEIF image.']);
        }

        $path = $file->store('website-originals/'.$lodge->id, 'local');
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

    public function destroy(Lodge $lodge, MediaAsset $media, WebsiteSectionCatalog $catalog)
    {
        $this->allowAsset($lodge, $media);
        $referenced = WebsiteSection::query()->where('lodge_id', $lodge->id)
            ->whereHas('version', fn ($query) => $query->whereIn('status', ['draft', 'published']))
            ->get()->contains(fn ($section) => in_array($media->id, $catalog->mediaIds($section->configuration), true));
        if ($referenced) {
            throw ValidationException::withMessages(['media' => 'Media used by a draft or published page cannot be deleted.']);
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

    private function allowAsset(Lodge $lodge, MediaAsset $media): void
    {
        abort_unless($media->lodge_id === $lodge->id, 404);
        $this->allowLodge($lodge, 'website.manage');
    }
}
