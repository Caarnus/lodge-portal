<?php

namespace App\Models;

use App\Enums\ContentVersionStatus;
use Database\Factories\NewsletterIssueVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsletterIssueVersion extends Model
{
    /** @use HasFactory<NewsletterIssueVersionFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => ContentVersionStatus::class, 'publication_date' => 'date', 'published_at' => 'datetime'];
    }

    public function issue()
    {
        return $this->belongsTo(NewsletterIssue::class, 'newsletter_issue_id');
    }

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function coverMediaAsset()
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_asset_id');
    }

    public function document()
    {
        return $this->belongsTo(NewsletterDocument::class, 'newsletter_document_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function distributionRuns()
    {
        return $this->hasMany(CommunicationDistributionRun::class);
    }
}
