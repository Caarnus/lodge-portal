<?php

namespace App\Models;

use App\Enums\WebsitePageStatus;
use Illuminate\Database\Eloquent\Model;

class WebsitePageVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => WebsitePageStatus::class,
            'is_home' => 'boolean',
            'show_in_navigation' => 'boolean',
            'is_navigation_container' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function page()
    {
        return $this->belongsTo(WebsitePage::class, 'website_page_id');
    }

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function sections()
    {
        return $this->hasMany(WebsiteSection::class)->orderBy('sort_order');
    }

    public function navigationParent()
    {
        return $this->belongsTo(WebsitePage::class, 'navigation_parent_page_id');
    }
}
