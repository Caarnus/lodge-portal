<?php

namespace App\Models;

use App\Enums\ContentVersionStatus;
use App\Enums\GalleryVisibility;
use Database\Factories\GalleryAlbumVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GalleryAlbumVersion extends Model
{
    /** @use HasFactory<GalleryAlbumVersionFactory> */
    use HasFactory;

    protected $guarded = [];

    public function album()
    {
        return $this->belongsTo(GalleryAlbum::class, 'gallery_album_id');
    }

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function coverPhoto()
    {
        return $this->belongsTo(GalleryAlbumPhoto::class, 'cover_photo_id');
    }

    public function photos()
    {
        return $this->hasMany(GalleryAlbumPhoto::class)->orderBy('sort_order');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    protected function casts(): array
    {
        return ['status' => ContentVersionStatus::class, 'visibility' => GalleryVisibility::class, 'published_at' => 'datetime'];
    }
}
