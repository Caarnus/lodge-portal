<?php

namespace App\Models;

use App\Enums\ContentVersionStatus;
use Database\Factories\NewsletterIssueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NewsletterIssue extends Model
{
    /** @use HasFactory<NewsletterIssueFactory> */
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
        return $this->hasMany(NewsletterIssueVersion::class);
    }

    public function draft()
    {
        return $this->hasOne(NewsletterIssueVersion::class)->where('status', ContentVersionStatus::Draft);
    }

    public function published()
    {
        return $this->hasOne(NewsletterIssueVersion::class)->where('status', ContentVersionStatus::Published);
    }
}
