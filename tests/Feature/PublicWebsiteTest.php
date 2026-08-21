<?php

namespace Tests\Feature;

use App\Models\Lodge;
use App\Models\MediaAsset;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WebsitePage;
use App\Models\WebsitePageVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    private function userFor(Lodge $lodge, array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::create(['lodge_id' => $lodge->id, 'name' => 'Website '.fake()->unique()->word()]);
        foreach ($permissions as $key) {
            $permission = Permission::firstOrCreate(['key' => $key], ['name' => $key]);
            $role->permissions()->attach($permission);
        }
        DB::table('lodge_user_roles')->insert([
            'lodge_id' => $lodge->id, 'user_id' => $user->id, 'role_id' => $role->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user;
    }

    private function pagePayload(array $changes = []): array
    {
        return array_merge([
            'title' => 'Home', 'slug' => 'home', 'is_home' => true,
            'show_in_navigation' => true, 'navigation_order' => 0,
            'navigation_parent_page_id' => null,
        ], $changes);
    }

    private function createPage(Lodge $lodge, User $user, array $changes = []): WebsitePage
    {
        $this->actingAs($user)->post("/lodges/{$lodge->id}/website/pages", $this->pagePayload($changes))->assertRedirect();

        return WebsitePage::query()->where('lodge_id', $lodge->id)->latest('id')->firstOrFail();
    }

    public function test_draft_is_private_until_transactional_publish_and_sites_are_isolated(): void
    {
        $a = Lodge::factory()->create(['slug' => 'alpha']);
        $b = Lodge::factory()->create(['slug' => 'bravo']);
        $adminA = $this->userFor($a, ['website.manage', 'website.publish']);
        $adminB = $this->userFor($b, ['website.manage', 'website.publish']);
        $pageA = $this->createPage($a, $adminA);
        $pageB = $this->createPage($b, $adminB);

        $this->get('/l/alpha')->assertNotFound();
        $this->actingAs($adminA)->post("/lodges/{$a->id}/website/pages/{$pageA->id}/sections", [
            'type' => 'rich_text', 'configuration' => ['html' => '<h2>Alpha content</h2>'],
        ])->assertRedirect();
        $this->actingAs($adminA)->post("/lodges/{$a->id}/website/pages/{$pageA->id}/publish")->assertRedirect();
        $this->actingAs($adminB)->post("/lodges/{$b->id}/website/pages/{$pageB->id}/publish")->assertRedirect();

        $this->get('/l/alpha')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('public/Website')->where('lodge.id', $a->id)->where('page.sections.0.configuration.html', '<h2>Alpha content</h2>'));
        $this->get('/l/bravo')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('public/Website')->where('lodge.id', $b->id)->missing('page.sections.0'));
    }

    public function test_editing_published_page_creates_draft_without_changing_public_content(): void
    {
        $lodge = Lodge::factory()->create();
        $admin = $this->userFor($lodge, ['website.manage', 'website.publish']);
        $page = $this->createPage($lodge, $admin);
        $this->actingAs($admin)->post("/lodges/{$lodge->id}/website/pages/{$page->id}/publish");

        $this->actingAs($admin)->get("/lodges/{$lodge->id}/website/pages/{$page->id}/edit")->assertOk();
        $draft = $page->fresh()->draft()->firstOrFail();
        $this->actingAs($admin)->put("/lodges/{$lodge->id}/website/pages/{$page->id}", $this->pagePayload(['title' => 'Private revision']))->assertRedirect();

        $this->get("/l/{$lodge->slug}")->assertInertia(fn (Assert $response) => $response->where('page.title', 'Home'));
        $this->assertSame('Private revision', $draft->fresh()->title);
        $this->assertDatabaseHas('audit_events', ['action' => 'website.page_updated', 'lodge_id' => $lodge->id]);
    }

    public function test_disabled_lodges_and_cross_lodge_resource_ids_are_rejected(): void
    {
        $a = Lodge::factory()->create();
        $b = Lodge::factory()->create();
        $admin = $this->userFor($a, ['website.manage', 'website.publish']);
        $pageB = WebsitePage::create(['lodge_id' => $b->id]);

        $this->actingAs($admin)->get("/lodges/{$b->id}/website")->assertForbidden();
        $this->actingAs($admin)->get("/lodges/{$a->id}/website/pages/{$pageB->id}/edit")->assertNotFound();

        $pageA = $this->createPage($a, $admin);
        $this->actingAs($admin)->post("/lodges/{$a->id}/website/pages/{$pageA->id}/publish");
        $a->update(['status' => 'disabled']);
        $this->get("/l/{$a->slug}")->assertNotFound();
    }

    public function test_manage_and_publish_permissions_are_independent(): void
    {
        $lodge = Lodge::factory()->create();
        $manager = $this->userFor($lodge, ['website.manage']);
        $publisher = $this->userFor($lodge, ['website.publish']);
        $page = $this->createPage($lodge, $manager);

        $this->actingAs($manager)->post("/lodges/{$lodge->id}/website/pages/{$page->id}/publish")->assertForbidden();
        $this->actingAs($publisher)->post("/lodges/{$lodge->id}/website/pages/{$page->id}/publish")->assertRedirect();
        $this->actingAs($publisher)->get("/lodges/{$lodge->id}/website")->assertForbidden();
    }

    public function test_rich_text_is_sanitized_and_custom_html_is_platform_only(): void
    {
        $lodge = Lodge::factory()->create();
        $admin = $this->userFor($lodge, ['website.manage']);
        $page = $this->createPage($lodge, $admin);
        $url = "/lodges/{$lodge->id}/website/pages/{$page->id}/sections";

        $this->actingAs($admin)->post($url, ['type' => 'rich_text', 'configuration' => ['html' => '<p onclick="bad()">Safe</p><script>alert(1)</script><a href="javascript:bad()">bad</a>']])->assertRedirect();
        $html = $page->draft()->firstOrFail()->sections()->firstOrFail()->configuration['html'];
        $this->assertStringContainsString('Safe', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('javascript:', $html);

        $this->actingAs($admin)->post($url, ['type' => 'custom_html', 'configuration' => ['html' => '<p>Denied</p>']])->assertSessionHasErrors('type');
        $platform = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($platform)->post($url, ['type' => 'custom_html', 'configuration' => ['html' => '<p>Allowed</p>']])->assertRedirect();
    }

    public function test_incomplete_sections_can_be_added_to_a_draft_and_must_be_completed_before_publish(): void
    {
        $lodge = Lodge::factory()->create();
        $admin = $this->userFor($lodge, ['website.manage', 'website.publish']);
        $page = $this->createPage($lodge, $admin);
        $url = "/lodges/{$lodge->id}/website/pages/{$page->id}/sections";

        $this->actingAs($admin)->post($url, ['type' => 'hero'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post($url, ['type' => 'image'])->assertSessionHasNoErrors();

        $sections = $page->draft()->firstOrFail()->sections;
        $this->assertSame(['hero', 'image'], $sections->pluck('type')->all());
        $this->assertNull($sections->last()->configuration['media_id']);
        $this->actingAs($admin)->post("/lodges/{$lodge->id}/website/pages/{$page->id}/publish")
            ->assertSessionHasErrors();
    }

    public function test_navigation_cycles_are_rejected_during_publish(): void
    {
        $lodge = Lodge::factory()->create();
        $admin = $this->userFor($lodge, ['website.manage', 'website.publish']);
        $a = $this->createPage($lodge, $admin, ['title' => 'A', 'slug' => 'a']);
        $b = $this->createPage($lodge, $admin, ['title' => 'B', 'slug' => 'b', 'is_home' => false]);
        $this->actingAs($admin)->put("/lodges/{$lodge->id}/website/pages/{$a->id}", $this->pagePayload(['title' => 'A', 'slug' => 'a', 'navigation_parent_page_id' => $b->id]));
        $this->actingAs($admin)->put("/lodges/{$lodge->id}/website/pages/{$b->id}", $this->pagePayload(['title' => 'B', 'slug' => 'b', 'is_home' => false, 'navigation_parent_page_id' => $a->id]));

        $this->actingAs($admin)->post("/lodges/{$lodge->id}/website/pages/{$a->id}/publish")->assertSessionHasErrors('navigation_parent_page_id');
        $this->assertDatabaseMissing('website_page_versions', ['website_page_id' => $a->id, 'status' => 'published']);
    }

    public function test_default_template_is_masonic_oriented_and_only_applies_to_empty_site(): void
    {
        $lodge = Lodge::factory()->create(['name' => 'Compass Lodge']);
        $admin = $this->userFor($lodge, ['website.manage']);
        $this->actingAs($admin)->post("/lodges/{$lodge->id}/website/template")->assertRedirect();

        $this->assertSame(5, $lodge->websitePages()->count());
        $this->assertDatabaseHas('website_page_versions', ['lodge_id' => $lodge->id, 'title' => 'Home', 'is_home' => true, 'status' => 'draft']);
        $welcome = WebsitePageVersion::query()->where('lodge_id', $lodge->id)->where('title', 'Home')->firstOrFail()->sections()->where('type', 'rich_text')->firstOrFail();
        $this->assertStringContainsString('Masonic lodge', $welcome->configuration['html']);

        $this->actingAs($admin)->post("/lodges/{$lodge->id}/website/template")->assertSessionHasErrors('template');
        $this->assertSame(5, $lodge->websitePages()->count());
    }

    public function test_uploaded_image_original_is_private_and_normalized_derivative_is_public(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $lodge = Lodge::factory()->create();
        $admin = $this->userFor($lodge, ['website.manage']);

        $this->actingAs($admin)->post("/lodges/{$lodge->id}/website/media", [
            'file' => UploadedFile::fake()->image('phone-photo.jpg', 1600, 1200),
            'alt_text' => 'Lodge exterior at sunset',
        ])->assertRedirect();

        $asset = MediaAsset::firstOrFail();
        $this->assertSame('ready', $asset->processing_status->value);
        Storage::disk('local')->assertExists($asset->original_path);
        Storage::disk('public')->assertExists($asset->derivative_path);
        $this->assertSame('Lodge exterior at sunset', $asset->alt_text);
        $this->assertLessThanOrEqual(2400, max($asset->width, $asset->height));
    }

    public function test_non_home_page_soft_delete_removes_public_route_and_restore_recovers_it(): void
    {
        $lodge = Lodge::factory()->create();
        $admin = $this->userFor($lodge, ['website.manage', 'website.publish']);
        $home = $this->createPage($lodge, $admin);
        $about = $this->createPage($lodge, $admin, ['title' => 'About', 'slug' => 'about', 'is_home' => false]);
        $this->actingAs($admin)->post("/lodges/{$lodge->id}/website/pages/{$home->id}/publish");
        $this->actingAs($admin)->post("/lodges/{$lodge->id}/website/pages/{$about->id}/publish");
        $this->get("/l/{$lodge->slug}/about")->assertOk();

        $this->actingAs($admin)->delete("/lodges/{$lodge->id}/website/pages/{$about->id}")->assertRedirect();
        $this->assertSoftDeleted($about);
        $this->get("/l/{$lodge->slug}/about")->assertNotFound();

        $this->actingAs($admin)->post("/lodges/{$lodge->id}/website/deleted-pages/{$about->id}/restore")->assertRedirect();
        $this->assertNotSoftDeleted($about);
        $this->get("/l/{$lodge->slug}/about")->assertOk();
        $this->assertDatabaseHas('audit_events', ['action' => 'website.page_restored', 'lodge_id' => $lodge->id]);
    }
}
