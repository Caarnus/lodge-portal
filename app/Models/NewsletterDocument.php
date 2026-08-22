<?php

namespace App\Models;

use Database\Factories\NewsletterDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NewsletterDocument extends Model
{
    /** @use HasFactory<NewsletterDocumentFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function issueVersions()
    {
        return $this->hasMany(NewsletterIssueVersion::class);
    }
}
