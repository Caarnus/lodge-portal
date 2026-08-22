<?php

namespace Database\Factories;

use App\Enums\ContentVersionStatus;
use App\Enums\GalleryVisibility;
use App\Models\GalleryAlbum;
use App\Models\GalleryAlbumVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class GalleryAlbumVersionFactory extends Factory
{
    protected $model = GalleryAlbumVersion::class;

    public function definition(): array
    {
        $album = GalleryAlbum::factory()->create();

        return [
            'lodge_id' => $album->lodge_id,
            'gallery_album_id' => $album->id,
            'status' => ContentVersionStatus::Draft,
            'title' => fake()->sentence(4),
            'visibility' => GalleryVisibility::Public,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => ContentVersionStatus::Published, 'published_at' => now()]);
    }
}
