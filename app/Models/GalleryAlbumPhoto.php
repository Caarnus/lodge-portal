<?php

namespace App\Models;

use Database\Factories\GalleryAlbumPhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GalleryAlbumPhoto extends Model
{
    /** @use HasFactory<GalleryAlbumPhotoFactory> */
    use HasFactory;

    protected $guarded = [];

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function version()
    {
        return $this->belongsTo(GalleryAlbumVersion::class, 'gallery_album_version_id');
    }

    public function mediaAsset()
    {
        return $this->belongsTo(MediaAsset::class);
    }
}
