<?php

namespace App\Services;

use App\Enums\WebsitePageStatus;
use App\Models\User;
use App\Models\WebsitePage;
use App\Models\WebsitePageVersion;
use Illuminate\Support\Facades\DB;

class WebsiteDrafts
{
    public function for(WebsitePage $page, User $user): WebsitePageVersion
    {
        if ($draft = $page->draft()->with('sections')->first()) {
            return $draft;
        }

        return DB::transaction(function () use ($page, $user) {
            $published = $page->published()->with('sections')->firstOrFail();
            $draft = $published->replicate(['status', 'published_at', 'published_by']);
            $draft->status = WebsitePageStatus::Draft;
            $draft->created_by = $user->id;
            $draft->published_at = null;
            $draft->published_by = null;
            $draft->save();
            foreach ($published->sections as $section) {
                $draft->sections()->create($section->only(['lodge_id', 'type', 'sort_order', 'configuration']));
            }

            return $draft->load('sections');
        });
    }
}
