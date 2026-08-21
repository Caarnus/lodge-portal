<?php

namespace App\Models;

use App\Enums\WebsitePageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WebsitePage extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function versions()
    {
        return $this->hasMany(WebsitePageVersion::class);
    }

    public function draft()
    {
        return $this->hasOne(WebsitePageVersion::class)->where('status', WebsitePageStatus::Draft);
    }

    public function published()
    {
        return $this->hasOne(WebsitePageVersion::class)->where('status', WebsitePageStatus::Published);
    }
}
