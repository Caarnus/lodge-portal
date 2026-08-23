<?php

namespace App\Services;

use App\Domain\Galleries\MediaExposureService;
use App\Enums\ContentVersionStatus;
use App\Enums\GalleryVisibility;
use App\Enums\MediaProcessingStatus;
use App\Models\GalleryAlbum;
use App\Models\GalleryAlbumVersion;
use App\Models\Lodge;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GalleryPublisher
{
    public function __construct(private readonly MediaExposureService $media) {}

    public function create(Lodge $lodge, User $user, array $data): GalleryAlbum
    {
        return DB::transaction(function () use ($lodge, $user, $data) {
            $album = GalleryAlbum::create(['lodge_id' => $lodge->id, 'slug' => $data['slug'], 'created_by' => $user->id]);
            $album->versions()->create($this->data($lodge, $user, $data));
            Audit::record('gallery.album_created', $album, $lodge);

            return $album;
        });
    }

    public function draftFor(GalleryAlbum $album, User $user): GalleryAlbumVersion
    {
        if ($draft = $album->draft()->first()) {
            return $draft;
        }

        return DB::transaction(function () use ($album, $user) {
            $album = GalleryAlbum::query()->lockForUpdate()->findOrFail($album->id);
            if ($draft = $album->draft()->first()) {
                return $draft;
            } $published = $album->published()->with('photos')->firstOrFail();
            $draft = $published->replicate(['status', 'published_at', 'published_by']);
            $draft->status = ContentVersionStatus::Draft;
            $draft->created_by = $user->id;
            $draft->published_at = null;
            $draft->published_by = null;
            $draft->save();
            foreach ($published->photos as $photo) {
                $draft->photos()->create($photo->only(['lodge_id', 'media_asset_id', 'caption', 'sort_order']));
            } if ($published->cover_photo_id) {
                $draft->update(['cover_photo_id' => $draft->photos()->where('media_asset_id', $published->coverPhoto?->media_asset_id)->value('id')]);
            }

            return $draft;
        });
    }

    public function update(GalleryAlbum $album, Lodge $lodge, User $user, array $data): GalleryAlbumVersion
    {
        return DB::transaction(function () use ($album, $lodge, $user, $data) {
            $album = GalleryAlbum::query()->lockForUpdate()->findOrFail($album->id);
            $draft = $this->draftFor($album, $user);
            if (($data['cover_photo_id'] ?? null) && ! $draft->photos()->whereKey($data['cover_photo_id'])->exists()) {
                throw ValidationException::withMessages(['cover_photo_id' => 'Cover photo must belong to this album draft.']);
            }
            $before = $draft->toArray();
            $album->update(['slug' => $data['slug']]);
            $draft->update($this->data($lodge, $user, $data, false));
            Audit::record('gallery.album_updated', $album, $lodge, $before, $draft->fresh()->toArray());

            return $draft->fresh();
        });
    }

    public function publish(GalleryAlbum $album, User $user): GalleryAlbumVersion
    {
        return DB::transaction(function () use ($album, $user) {
            $album = GalleryAlbum::query()->lockForUpdate()->findOrFail($album->id);
            $draft = $album->draft()->with('photos.mediaAsset')->lockForUpdate()->firstOrFail();
            if (! $draft->photos->count()) {
                throw ValidationException::withMessages(['photos' => 'Add at least one photo before publishing.']);
            } foreach ($draft->photos as $photo) {
                if ($photo->mediaAsset->processing_status !== MediaProcessingStatus::Ready) {
                    throw ValidationException::withMessages(['photos' => 'All photos must be ready before publishing.']);
                }
            } $old = $album->published()->first();
            $old?->update(['status' => ContentVersionStatus::Archived]);
            $draft->update(['status' => ContentVersionStatus::Published, 'published_at' => now(), 'published_by' => $user->id]);
            foreach ($draft->photos as $photo) {
                if ($draft->visibility === GalleryVisibility::Public) {
                    $this->media->makePublic($photo->mediaAsset);
                } else {
                    $this->media->restrictToPrivate($photo->mediaAsset);
                }
            } Audit::record('gallery.album_published', $album, $album->lodge);

            return $draft->fresh();
        });
    }

    public function unpublish(GalleryAlbum $album, User $user): void
    {
        DB::transaction(function () use ($album, $user) {
            $published = $album->published()->with('photos')->lockForUpdate()->firstOrFail();
            $draft = $published->replicate(['status', 'published_at', 'published_by']);
            $draft->status = ContentVersionStatus::Draft;
            $draft->created_by = $user->id;
            $draft->published_at = null;
            $draft->published_by = null;
            $draft->save();
            foreach ($published->photos as $photo) {
                $draft->photos()->create($photo->only(['lodge_id', 'media_asset_id', 'caption', 'sort_order']));
            } $published->update(['status' => ContentVersionStatus::Archived]);
        });
    }

    private function data(Lodge $lodge, User $user, array $data, bool $create = true): array
    {
        $cover = $data['cover_photo_id'] ?? null;

        return array_filter(['lodge_id' => $lodge->id, 'status' => ContentVersionStatus::Draft, 'title' => $data['title'], 'description' => $data['description'] ?? null, 'visibility' => $data['visibility'] ?? GalleryVisibility::Public, 'cover_photo_id' => $cover, 'created_by' => $create ? $user->id : null], fn ($v, $k) => $create || $k !== 'created_by' || $v !== null);
    }
}
