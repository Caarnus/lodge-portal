<?php

namespace App\Models;

use App\Enums\ContentVersionStatus;
use Database\Factories\GalleryAlbumFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GalleryAlbum extends Model
{
    /** @use HasFactory<GalleryAlbumFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions()
    {
        return $this->hasMany(GalleryAlbumVersion::class);
    }

    public function draft()
    {
        return $this->hasOne(GalleryAlbumVersion::class)->where('status', ContentVersionStatus::Draft);
    }

    public function published()
    {
        return $this->hasOne(GalleryAlbumVersion::class)->where('status', ContentVersionStatus::Published);
    }
}
