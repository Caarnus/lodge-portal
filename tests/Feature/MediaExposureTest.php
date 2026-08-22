<?php

namespace Tests\Feature;

use App\Domain\Galleries\MediaExposureService;
use App\Enums\ContentVersionStatus;
use App\Enums\GalleryVisibility;
use App\Enums\MediaProcessingStatus;
use App\Models\GalleryAlbum;
use App\Models\GalleryAlbumPhoto;
use App\Models\GalleryAlbumVersion;
use App\Models\Lodge;
use App\Models\MediaAsset;
use App\Models\NewsletterIssue;
use App\Models\NewsletterIssueVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MediaExposureTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_derivative_response_requires_authorization_and_is_not_cached(): void
    {
        Storage::fake('local');
        $asset = MediaAsset::factory()->create([
            'private_derivative_path' => 'website-private/ready.jpg',
            'processing_status' => MediaProcessingStatus::Ready,
        ]);
        Storage::disk('local')->put($asset->private_derivative_path, 'private image');

        $response = app(MediaExposureService::class)->privateDerivativeResponse($asset, true);

        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));

        $this->expectException(HttpException::class);
        app(MediaExposureService::class)->privateDerivativeResponse($asset, false);
    }

    public function test_private_media_revokes_its_public_copy_when_no_public_reference_exists(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $asset = MediaAsset::factory()->create([
            'visibility' => 'private',
            'derivative_path' => 'website-media/public.jpg',
            'private_derivative_path' => 'website-private/private.jpg',
        ]);
        Storage::disk('local')->put($asset->private_derivative_path, 'private image');
        Storage::disk('public')->put($asset->derivative_path, 'public image');

        app(MediaExposureService::class)->syncPublicCopy($asset);

        $this->assertNull($asset->fresh()->derivative_path);
        Storage::disk('public')->assertMissing('website-media/public.jpg');
    }

    public function test_public_gallery_reference_blocks_asset_restriction(): void
    {
        $lodge = Lodge::factory()->create();
        $asset = MediaAsset::factory()->create(['lodge_id' => $lodge->id, 'visibility' => 'public']);
        $album = GalleryAlbum::factory()->create(['lodge_id' => $lodge->id]);
        $version = GalleryAlbumVersion::factory()->create([
            'lodge_id' => $lodge->id,
            'gallery_album_id' => $album->id,
            'status' => ContentVersionStatus::Published,
            'visibility' => GalleryVisibility::Public,
        ]);
        GalleryAlbumPhoto::factory()->create([
            'lodge_id' => $lodge->id,
            'gallery_album_version_id' => $version->id,
            'media_asset_id' => $asset->id,
        ]);

        $this->expectException(\LogicException::class);
        app(MediaExposureService::class)->restrictToPrivate($asset);
    }

    public function test_newsletter_cover_reference_blocks_media_deletion(): void
    {
        $lodge = Lodge::factory()->create();
        $asset = MediaAsset::factory()->create(['lodge_id' => $lodge->id]);
        $issue = NewsletterIssue::factory()->create(['lodge_id' => $lodge->id]);
        NewsletterIssueVersion::factory()->create([
            'lodge_id' => $lodge->id,
            'newsletter_issue_id' => $issue->id,
            'cover_media_asset_id' => $asset->id,
        ]);

        $this->assertTrue(app(MediaExposureService::class)->hasAnyReferences($asset));
    }
}
