<?php

namespace App\Services;

use App\Enums\WebsitePageStatus;
use App\Models\Lodge;
use App\Models\User;
use App\Models\WebsitePage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DefaultWebsiteTemplate
{
    public function apply(Lodge $lodge, User $user): void
    {
        if ($lodge->websitePages()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['template' => 'Template can only be applied to an empty website.']);
        }

        DB::transaction(function () use ($lodge, $user) {
            foreach ($this->pages($lodge) as $pageData) {
                $page = WebsitePage::create(['lodge_id' => $lodge->id]);
                $version = $page->versions()->create([
                    'lodge_id' => $lodge->id,
                    'status' => WebsitePageStatus::Draft,
                    'title' => $pageData['title'],
                    'slug' => $pageData['slug'],
                    'is_home' => $pageData['is_home'],
                    'navigation_order' => $pageData['order'],
                    'created_by' => $user->id,
                ]);
                foreach ($pageData['sections'] as $order => $section) {
                    $version->sections()->create([
                        'lodge_id' => $lodge->id,
                        'type' => $section['type'],
                        'sort_order' => $order,
                        'configuration' => $section['configuration'],
                    ]);
                }
            }
            Audit::record('website.template_applied', $lodge, $lodge, null, ['template' => 'default', 'version' => 1]);
        });
    }

    private function pages(Lodge $lodge): array
    {
        return [
            ['title' => 'Home', 'slug' => 'home', 'is_home' => true, 'order' => 0, 'sections' => [
                ['type' => 'hero', 'configuration' => ['heading' => $lodge->name, 'body' => $lodge->tag_line ?: 'Brotherhood, fellowship, and service in our community.', 'media_id' => null]],
                ['type' => 'rich_text', 'configuration' => ['html' => '<h2>Welcome</h2><p>Discover a welcoming Masonic lodge dedicated to fellowship, personal growth, and service. Learn more about our lodge and how to connect with us.</p>']],
            ]],
            ['title' => 'About', 'slug' => 'about', 'is_home' => false, 'order' => 10, 'sections' => [
                ['type' => 'rich_text', 'configuration' => ['html' => '<h1>About Our Lodge</h1><p>Use this page to share your lodge’s history, values, and work in the community.</p>']],
            ]],
            ['title' => 'Events', 'slug' => 'events', 'is_home' => false, 'order' => 20, 'sections' => [
                ['type' => 'events_placeholder', 'configuration' => ['heading' => 'Upcoming Events', 'body' => 'Event listings are coming soon. Contact the lodge for current information.']],
            ]],
            ['title' => 'Officers', 'slug' => 'officers', 'is_home' => false, 'order' => 30, 'sections' => [
                ['type' => 'officers_placeholder', 'configuration' => ['heading' => 'Lodge Officers', 'body' => 'Officer information will be available soon.']],
            ]],
            ['title' => 'Past Masters', 'slug' => 'past-masters', 'is_home' => false, 'order' => 35, 'sections' => [
                ['type' => 'past_masters_placeholder', 'configuration' => ['heading' => 'Past Masters', 'body' => 'Our lodge is grateful for the service of these Past Masters.']],
            ]],
            ['title' => 'Contact', 'slug' => 'contact', 'is_home' => false, 'order' => 40, 'sections' => [
                ['type' => 'contact_information', 'configuration' => ['heading' => 'Contact Us', 'body' => 'We welcome questions from members and visitors.']],
                ['type' => 'meeting_information', 'configuration' => ['heading' => 'Meeting Information', 'body' => 'Contact the lodge to confirm meeting details before visiting.']],
            ]],
        ];
    }
}
