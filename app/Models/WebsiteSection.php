<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteSection extends Model
{
    protected $guarded = [];

    public function version()
    {
        return $this->belongsTo(WebsitePageVersion::class, 'website_page_version_id');
    }

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    protected function casts(): array
    {
        return ['configuration' => 'array'];
    }
}
