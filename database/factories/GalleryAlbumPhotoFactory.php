<?php

namespace Database\Factories;

use App\Models\GalleryAlbumPhoto;
use App\Models\GalleryAlbumVersion;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

class GalleryAlbumPhotoFactory extends Factory
{
    protected $model = GalleryAlbumPhoto::class;

    public function definition(): array
    {
        $version = GalleryAlbumVersion::factory()->create();
        $asset = MediaAsset::factory()->create(['lodge_id' => $version->lodge_id]);

        return ['lodge_id' => $version->lodge_id, 'gallery_album_version_id' => $version->id, 'media_asset_id' => $asset->id, 'caption' => fake()->sentence(), 'sort_order' => 0];
    }
}
