<?php

namespace Database\Factories;

use App\Enums\MediaProcessingStatus;
use App\Models\Lodge;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MediaAssetFactory extends Factory
{
    protected $model = MediaAsset::class;

    public function definition(): array
    {
        $path = 'website-media/'.fake()->uuid().'.jpg';

        return [
            'lodge_id' => Lodge::factory(),
            'original_name' => 'photo.jpg',
            'original_path' => 'website-originals/'.fake()->uuid().'.jpg',
            'derivative_path' => $path,
            'private_derivative_path' => 'website-private/'.Str::uuid().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'width' => 800,
            'height' => 600,
            'alt_text' => fake()->sentence(4),
            'visibility' => 'public',
            'processing_status' => MediaProcessingStatus::Ready,
            'processed_at' => now(),
            'is_platform_shared' => false,
        ];
    }
}
