<?php

namespace App\Domain\Galleries;

use App\Enums\ContentVersionStatus;
use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Enums\GalleryVisibility;
use App\Enums\MediaProcessingStatus;
use App\Models\Event;
use App\Models\GalleryAlbumPhoto;
use App\Models\Lodge;
use App\Models\MediaAsset;
use App\Models\NewsletterIssueVersion;
use App\Models\WebsiteSection;
use App\Services\WebsiteSectionCatalog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaExposureService
{
    public function __construct(private readonly WebsiteSectionCatalog $sections)
    {
    }

    public function syncPublicCopy(MediaAsset $asset): void
    {
        $asset = $asset->fresh();
        if (!$asset || $asset->processing_status !== MediaProcessingStatus::Ready || !$asset->private_derivative_path) {
            return;
        }

        if ($asset->visibility === 'public') {
            if (!$asset->derivative_path || !Storage::disk('public')->exists($asset->derivative_path)) {
                $target = 'website-media/' . $asset->lodge_id . '/' . Str::uuid() . '.jpg';
                Storage::disk('public')->put($target, Storage::disk('local')->get($asset->private_derivative_path), ['visibility' => 'public']);
                $asset->update(['derivative_path' => $target]);
            }

            return;
        }

        $this->revokePublicCopy($asset);
    }

    public function restrictToPrivate(MediaAsset $asset): void
    {
        if ($this->hasPublicReferences($asset)) {
            throw new \LogicException('Media has a published public reference and cannot be restricted.');
        }

        $asset->update(['visibility' => 'private']);
        $this->revokePublicCopy($asset);
    }

    public function makePublic(MediaAsset $asset): void
    {
        $asset->update(['visibility' => 'public']);
        $this->syncPublicCopy($asset);
    }

    public function hasAnyReferences(MediaAsset $asset): bool
    {
        return $this->websiteReferenceExists($asset, [ContentVersionStatus::Draft, ContentVersionStatus::Published])
            || Event::query()->where('lodge_id', $asset->lodge_id)->where('cover_media_asset_id', $asset->id)->whereNull('deleted_at')->exists()
            || NewsletterIssueVersion::query()->where('lodge_id', $asset->lodge_id)->where('cover_media_asset_id', $asset->id)->whereIn('status', [ContentVersionStatus::Draft, ContentVersionStatus::Published])->exists()
            || GalleryAlbumPhoto::query()->where('lodge_id', $asset->lodge_id)->where('media_asset_id', $asset->id)->whereHas('version', fn($query) => $query->whereIn('status', [ContentVersionStatus::Draft, ContentVersionStatus::Published]))->exists()
            || $this->brandingReferenceExists($asset);
    }

    public function hasPublicReferences(MediaAsset $asset): bool
    {
        return $this->websiteReferenceExists($asset, [ContentVersionStatus::Published])
            || Event::query()->where('lodge_id', $asset->lodge_id)->where('cover_media_asset_id', $asset->id)->where('status', EventStatus::Published)->where('visibility', EventVisibility::Public)->whereNull('deleted_at')->exists()
            || GalleryAlbumPhoto::query()->where('lodge_id', $asset->lodge_id)->where('media_asset_id', $asset->id)->whereHas('version', fn($query) => $query->where('status', ContentVersionStatus::Published)->where('visibility', GalleryVisibility::Public))->exists()
            || $this->brandingReferenceExists($asset);
    }

    public function privateDerivativeResponse(MediaAsset $asset, bool $authorized): StreamedResponse
    {
        abort_unless($authorized, 404);
        abort_unless($asset->processing_status === MediaProcessingStatus::Ready && $asset->private_derivative_path && Storage::disk('local')->exists($asset->private_derivative_path), 404);

        return Storage::disk('local')->response($asset->private_derivative_path, 'media.jpg', [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function revokePublicCopy(MediaAsset $asset): void
    {
        if ($asset->derivative_path) {
            Storage::disk('public')->delete($asset->derivative_path);
            $asset->update(['derivative_path' => null]);
        }
    }

    /** @param array<ContentVersionStatus> $statuses */
    private function websiteReferenceExists(MediaAsset $asset, array $statuses): bool
    {
        return WebsiteSection::query()->where('lodge_id', $asset->lodge_id)
            ->whereHas('version', fn($query) => $query->whereIn('status', $statuses))
            ->get()
            ->contains(fn(WebsiteSection $section) => in_array($asset->id, $this->sections->mediaIds($section->configuration), true));
    }

    private function brandingReferenceExists(MediaAsset $asset): bool
    {
        if (!$asset->derivative_path) {
            return false;
        }

        return Lodge::query()->whereKey($asset->lodge_id)
            ->where(fn($query) => $query->where('logo_path', $asset->derivative_path)->orWhere('seal_path', $asset->derivative_path))
            ->exists();
    }
}
