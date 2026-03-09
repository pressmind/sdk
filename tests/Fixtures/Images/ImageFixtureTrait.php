<?php

namespace Pressmind\Tests\Fixtures\Images;

use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;

/**
 * Trait for image processor tests: fixture paths, temp bucket, cropped section, pixel read.
 * Fixture images are created under tests/fixtures/images/ when missing (using GD).
 */
trait ImageFixtureTrait
{
    private static ?string $fixtureImagesPath = null;

    protected static function getFixtureImagesPath(): string
    {
        if (self::$fixtureImagesPath === null) {
            self::$fixtureImagesPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'Images';
        }
        return self::$fixtureImagesPath;
    }

    protected function getTestImagePath(): string
    {
        $path = self::getFixtureImagesPath() . DIRECTORY_SEPARATOR . 'test-landscape.jpg';
        $this->ensureTestLandscapeExists($path);
        return $path;
    }

    protected function getWatermarkPath(): string
    {
        $path = self::getFixtureImagesPath() . DIRECTORY_SEPARATOR . 'watermark.png';
        $this->ensureWatermarkExists($path);
        return $path;
    }

    protected function createTempBucket(): Bucket
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_image_test_' . uniqid('', true);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return new Bucket([
            'provider' => 'filesystem',
            'bucket' => $dir,
        ]);
    }

    protected function createTestImageFile(Bucket $bucket): File
    {
        $path = $this->getTestImagePath();
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('Failed to read fixture: ' . $path);
        }
        $file = new File($bucket);
        $file->name = 'test-landscape.jpg';
        $file->content = $content;
        $file->mimetype = 'image/jpeg';
        return $file;
    }

    protected function createCroppedSection(int $x, int $y, int $w, int $h): string
    {
        $path = $this->getTestImagePath();
        $fullContent = file_get_contents($path);
        if ($fullContent === false) {
            throw new \RuntimeException('Failed to read fixture: ' . $path);
        }

        if (extension_loaded('imagick')) {
            $im = new \Imagick();
            $im->readImageBlob($fullContent);
            $im->cropImage($w, $h, $x, $y);
            $im->setImageFormat('jpg');
            $blob = $im->getImageBlob();
            $im->destroy();
            return $blob;
        }

        if (extension_loaded('gd')) {
            $src = imagecreatefromstring($fullContent);
            if ($src === false) {
                throw new \RuntimeException('GD could not load fixture image');
            }
            $cropped = imagecreatetruecolor($w, $h);
            if ($cropped === false) {
                imagedestroy($src);
                throw new \RuntimeException('GD could not create crop image');
            }
            imagecopy($cropped, $src, 0, 0, $x, $y, $w, $h);
            ob_start();
            imagejpeg($cropped);
            $blob = ob_get_clean();
            imagedestroy($src);
            imagedestroy($cropped);
            return $blob;
        }

        throw new \RuntimeException('Need imagick or gd extension for createCroppedSection');
    }

    protected function getPixelColor(string $blob, int $x, int $y): array
    {
        if (extension_loaded('imagick')) {
            $im = new \Imagick();
            $im->readImageBlob($blob);
            $pixel = $im->getImagePixelColor($x, $y);
            $c = $pixel->getColor();
            $im->destroy();
            return [
                'r' => (int) round(($c['r'] ?? 0) * 255),
                'g' => (int) round(($c['g'] ?? 0) * 255),
                'b' => (int) round(($c['b'] ?? 0) * 255),
            ];
        }

        if (extension_loaded('gd')) {
            $img = imagecreatefromstring($blob);
            if ($img === false) {
                throw new \RuntimeException('GD could not load image blob');
            }
            $rgb = imagecolorat($img, $x, $y);
            imagedestroy($img);
            return [
                'r' => ($rgb >> 16) & 0xFF,
                'g' => ($rgb >> 8) & 0xFF,
                'b' => $rgb & 0xFF,
            ];
        }

        throw new \RuntimeException('Need imagick or gd extension for getPixelColor');
    }

    private function ensureTestLandscapeExists(string $path): void
    {
        if (file_exists($path)) {
            return;
        }
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('Fixture test-landscape.jpg missing and GD not available to generate it. Create 640x480 JPEG at: ' . $path);
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $w = 640;
        $h = 480;
        $img = imagecreatetruecolor($w, $h);
        if ($img === false) {
            throw new \RuntimeException('GD could not create test image');
        }
        $midX = (int) ($w / 2);
        $midY = (int) ($h / 2);
        imagefilledrectangle($img, 0, 0, $midX - 1, $midY - 1, imagecolorallocate($img, 220, 80, 80));
        imagefilledrectangle($img, $midX, 0, $w - 1, $midY - 1, imagecolorallocate($img, 80, 200, 80));
        imagefilledrectangle($img, 0, $midY, $midX - 1, $h - 1, imagecolorallocate($img, 80, 80, 220));
        imagefilledrectangle($img, $midX, $midY, $w - 1, $h - 1, imagecolorallocate($img, 220, 220, 80));
        imagejpeg($img, $path, 90);
        imagedestroy($img);
    }

    private function ensureWatermarkExists(string $path): void
    {
        if (file_exists($path)) {
            return;
        }
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('Fixture watermark.png missing and GD not available to generate it. Create 64x64 PNG at: ' . $path);
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $size = 64;
        $img = imagecreatetruecolor($size, $size);
        if ($img === false) {
            throw new \RuntimeException('GD could not create watermark image');
        }
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $transparent);
        $gray = imagecolorallocate($img, 180, 180, 180);
        imagefilledellipse($img, $size / 2, $size / 2, $size - 4, $size - 4, $gray);
        imagepng($img, $path);
        imagedestroy($img);
    }
}
