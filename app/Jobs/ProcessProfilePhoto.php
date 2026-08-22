<?php

namespace App\Jobs;

use App\Models\Person;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessProfilePhoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $personId, public readonly string $sourcePath) {}

    public function handle(): void
    {
        $person = Person::query()->find($this->personId);
        if (! $person || $person->merged_at || $person->is_deceased || $person->profile_photo_path !== $this->sourcePath) {
            return;
        }
        $person->update(['profile_photo_status' => 'processing', 'profile_photo_error' => null]);
        try {
            $source = Storage::disk('local')->path($this->sourcePath);
            $target = 'profile-photos/'.$person->id.'/'.Str::uuid().'.jpg';
            $bytes = class_exists(\Imagick::class) ? $this->imagick($source) : $this->gd($source);
            if (! Storage::disk('local')->put($target, $bytes)) {
                throw new \RuntimeException('The private profile derivative could not be stored.');
            }
            $person->refresh();
            if ($person->merged_at || $person->is_deceased || $person->profile_photo_path !== $this->sourcePath) {
                Storage::disk('local')->delete($target);

                return;
            }
            if ($person->profile_photo_derivative_path) {
                Storage::disk('local')->delete($person->profile_photo_derivative_path);
            }
            $person->update(['profile_photo_derivative_path' => $target, 'profile_photo_status' => 'ready', 'profile_photo_error' => null]);
        } catch (\Throwable $exception) {
            $person->update(['profile_photo_status' => 'failed', 'profile_photo_error' => str($exception->getMessage())->limit(1000)]);
        }
    }

    private function imagick(string $source): string
    {
        $image = new \Imagick($source);
        $image->setIteratorIndex(0);
        $image->autoOrient();
        $this->assertPixels($image->getImageWidth(), $image->getImageHeight());
        $image->thumbnailImage(800, 800, true);
        $image->stripImage();
        $image->setImageFormat('jpeg');
        $image->setImageCompressionQuality(85);
        $bytes = $image->getImagesBlob();
        $image->clear();

        return $bytes;
    }

    private function gd(string $source): string
    {
        $details = @getimagesize($source);
        if (! $details) {
            throw new \RuntimeException('Server image decoder does not support this file.');
        }
        [$width, $height] = $details;
        $this->assertPixels($width, $height);
        $image = @imagecreatefromstring((string) file_get_contents($source));
        if (! $image) {
            throw new \RuntimeException('Image could not be decoded.');
        }
        $scale = min(1, 800 / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $output = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($output, 0, 0, imagecolorallocate($output, 255, 255, 255));
        imagecopyresampled($output, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        ob_start();
        imagejpeg($output, null, 85);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);
        imagedestroy($output);

        return $bytes;
    }

    private function assertPixels(int $width, int $height): void
    {
        if ($width < 1 || $height < 1 || $width * $height > (int) config('website.max_pixels', 60_000_000)) {
            throw new \RuntimeException('Image exceeds the 60-megapixel decoded image limit.');
        }
    }
}
