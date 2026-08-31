<?php

namespace App\Models;

use App\Enums\MediaProcessingStatus;
use Database\Factories\MediaAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MediaAsset extends Model
{
    /** @use HasFactory<MediaAssetFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $appends = ['url'];

    public function getUrlAttribute(): ?string
    {
        return $this->derivative_path ? Storage::disk('public')->url($this->derivative_path) : null;
    }

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function galleryAlbumPhotos()
    {
        return $this->hasMany(GalleryAlbumPhoto::class);
    }

    public function newsletterCoverVersions()
    {
        return $this->hasMany(NewsletterIssueVersion::class, 'cover_media_asset_id');
    }

    protected function casts(): array
    {
        return [
            'processing_status' => MediaProcessingStatus::class,
            'processed_at' => 'datetime',
            'is_platform_shared' => 'boolean',
        ];
    }
}
