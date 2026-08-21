<?php

namespace App\Models;

use App\Enums\MediaProcessingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MediaAsset extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $appends = ['url'];

    protected function casts(): array
    {
        return [
            'processing_status' => MediaProcessingStatus::class,
            'processed_at' => 'datetime',
            'is_platform_shared' => 'boolean',
        ];
    }

    public function getUrlAttribute(): ?string
    {
        return $this->derivative_path ? Storage::disk('public')->url($this->derivative_path) : null;
    }

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }
}
