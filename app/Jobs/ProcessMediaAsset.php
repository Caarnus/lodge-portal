<?php

namespace App\Jobs;

use App\Domain\Galleries\MediaExposureService;
use App\Enums\MediaProcessingStatus;
use App\Models\MediaAsset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessMediaAsset implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $lodgeId, public readonly int $mediaAssetId)
    {
    }

    public function handle(MediaExposureService $exposure): void
    {
        $asset = MediaAsset::query()->whereKey($this->mediaAssetId)->where('lodge_id', $this->lodgeId)->firstOrFail();
        $asset->update(['processing_status' => MediaProcessingStatus::Processing, 'processing_error' => null]);

        try {
            $source = Storage::disk('local')->path($asset->original_path);
            $privateTargetPath = 'website-private/' . $asset->lodge_id . '/' . Str::uuid() . '.jpg';
            [$bytes, $width, $height] = class_exists(\Imagick::class)
                ? $this->withImagick($source)
                : $this->withGd($source);
            Storage::disk('local')->put($privateTargetPath, $bytes);
            $previousPrivatePath = $asset->private_derivative_path;
            $previousPublicPath = $asset->derivative_path;
            $asset->update([
                'derivative_path' => null,
                'private_derivative_path' => $privateTargetPath,
                'width' => $width,
                'height' => $height,
                'processing_status' => MediaProcessingStatus::Ready,
                'processing_error' => null,
                'processed_at' => now(),
            ]);
            if ($previousPrivatePath) {
                Storage::disk('local')->delete($previousPrivatePath);
            }
            if ($previousPublicPath) {
                Storage::disk('public')->delete($previousPublicPath);
            }
            $exposure->syncPublicCopy($asset);
        } catch (\Throwable $exception) {
            $asset->update([
                'processing_status' => MediaProcessingStatus::Failed,
                'processing_error' => str($exception->getMessage())->limit(1000),
            ]);
        }
    }

    private function withImagick(string $source): array
    {
        $image = new \Imagick($source);
        $image->setIteratorIndex(0);
        $image->autoOrient();
        $width = $image->getImageWidth();
        $height = $image->getImageHeight();
        $this->assertPixels($width, $height);
        $max = (int)config('website.max_derivative_dimension');
        if ($width > $max || $height > $max) {
            $image->thumbnailImage($max, $max, true);
        }
        $image->stripImage();
        $image->setImageFormat('jpeg');
        $image->setImageCompressionQuality(85);
        $result = [$image->getImagesBlob(), $image->getImageWidth(), $image->getImageHeight()];
        $image->clear();

        return $result;
    }

    private function assertPixels(int $width, int $height): void
    {
        $maxPixels = (int)config('website.max_pixels', 60_000_000);
        if ($maxPixels < 1) {
            throw new \RuntimeException('Media pixel limit is not configured correctly.');
        }
        if ($width < 1 || $height < 1 || $width * $height > $maxPixels) {
            throw new \RuntimeException('Image exceeds the 60-megapixel decoded image limit.');
        }
    }

    private function withGd(string $source): array
    {
        $details = @getimagesize($source);
        if (!$details) {
            throw new \RuntimeException('Server image decoder does not support this file.');
        }
        [$width, $height] = $details;
        $this->assertPixels($width, $height);
        $image = @imagecreatefromstring((string)file_get_contents($source));
        if (!$image) {
            throw new \RuntimeException('Image could not be decoded.');
        }
        $max = (int)config('website.max_derivative_dimension');
        $scale = min(1, $max / max($width, $height));
        $newWidth = max(1, (int)round($width * $scale));
        $newHeight = max(1, (int)round($height * $scale));
        $output = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($output, 0, 0, imagecolorallocate($output, 255, 255, 255));
        imagecopyresampled($output, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        ob_start();
        imagejpeg($output, null, 85);
        $bytes = (string)ob_get_clean();
        imagedestroy($image);
        imagedestroy($output);

        return [$bytes, $newWidth, $newHeight];
    }
}
