<?php

namespace Database\Factories;

use App\Models\GalleryAlbum;
use App\Models\Lodge;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GalleryAlbumFactory extends Factory
{
    protected $model = GalleryAlbum::class;

    public function definition(): array
    {
        return ['lodge_id' => Lodge::factory(), 'slug' => Str::slug(fake()->unique()->sentence(3))];
    }
}
