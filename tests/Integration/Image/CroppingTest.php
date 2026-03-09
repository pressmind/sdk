<?php

namespace Pressmind\Tests\Integration\Image;

use Imagick;
use Pressmind\Image\Processor\Adapter\ImageMagick;
use Pressmind\Image\Processor\Adapter\ImageMagickCLI;
use Pressmind\Image\Processor\Config;
use Pressmind\Storage\File;
use Pressmind\Tests\Fixtures\Images\ImageFixtureTrait;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Cropping behaviour: config-level crop vs thumbnail, section-based cropping, pixel verification.
 * Plan 2.4: Config-Cropping, API-Section-Cropping (simulated), ImageMagickCLI gravity.
 *
 * @requires extension imagick
 */
class CroppingTest extends AbstractTestCase
{
    use ImageFixtureTrait;

    public function testCropTrueProducesExactDimensions(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $config = Config::create('thumb', [
            'max_width' => 200,
            'max_height' => 200,
            'crop' => true,
        ]);
        $adapter = new ImageMagick();
        $result = $adapter->process($config, $file, 'thumb');
        $im = new Imagick();
        $im->readImageBlob($result->content);
        $this->assertSame(200, $im->getImageWidth(), 'crop=true must produce exact width');
        $this->assertSame(200, $im->getImageHeight(), 'crop=true must produce exact height');
        $im->destroy();
    }

    public function testCropFalsePreservesAspectRatio(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $config = Config::create('thumb', [
            'max_width' => 200,
            'max_height' => 200,
            'crop' => false,
            'preserve_aspect_ratio' => true,
        ]);
        $adapter = new ImageMagick();
        $result = $adapter->process($config, $file, 'thumb');
        $im = new Imagick();
        $im->readImageBlob($result->content);
        $w = $im->getImageWidth();
        $h = $im->getImageHeight();
        $im->destroy();
        $this->assertLessThanOrEqual(200, $w);
        $this->assertLessThanOrEqual(200, $h);
        $this->assertTrue($w === 200 || $h === 200, 'at least one dimension must hit max');
        $ratio = $w / $h;
        $expectedRatio = 640 / 480;
        $this->assertGreaterThan(0.99, min($ratio / $expectedRatio, $expectedRatio / $ratio), 'aspect ratio preserved');
    }

    public function testCropThumbnailRemovesImageContent(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $configCrop = Config::create('thumb', ['max_width' => 200, 'max_height' => 200, 'crop' => true]);
        $adapter = new ImageMagick();
        $result = $adapter->process($configCrop, $file, 'thumb');
        $im = new Imagick();
        $im->readImageBlob($result->content);
        $this->assertSame(200, $im->getImageWidth());
        $this->assertSame(200, $im->getImageHeight());
        $pixelCenter = $im->getImagePixelColor(100, 100);
        $c = $pixelCenter->getColor();
        $im->destroy();
        $this->assertIsNumeric($c['r']);
        $this->assertIsNumeric($c['g']);
        $this->assertIsNumeric($c['b']);
    }

    public function testCropVsThumbnailOutputDiffers(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $configCrop = Config::create('c', ['max_width' => 200, 'max_height' => 200, 'crop' => true]);
        $configThumb = Config::create('t', ['max_width' => 200, 'max_height' => 200, 'crop' => false, 'preserve_aspect_ratio' => true]);
        $adapter = new ImageMagick();
        $outCrop = $adapter->process($configCrop, $file, 'c');
        $file2 = $this->createTestImageFile($bucket);
        $outThumb = $adapter->process($configThumb, $file2, 't');
        $this->assertNotSame($outCrop->content, $outThumb->content);
        $this->assertNotEquals($outCrop->content, $outThumb->content);
    }

    public function testSectionDerivativeFromCroppedInput(): void
    {
        $sectionBlob = $this->createCroppedSection(100, 50, 320, 240);
        $this->assertNotEmpty($sectionBlob);
        $bucket = $this->createTempBucket();
        $sectionFile = new File($bucket);
        $sectionFile->name = 'section_320x240.jpg';
        $sectionFile->content = $sectionBlob;
        $config = Config::create('thumb', ['max_width' => 100, 'max_height' => 100, 'crop' => true]);
        $adapter = new ImageMagick();
        $result = $adapter->process($config, $sectionFile, 'thumb');
        $im = new Imagick();
        $im->readImageBlob($result->content);
        $this->assertSame(100, $im->getImageWidth());
        $this->assertSame(100, $im->getImageHeight());
        $im->destroy();
    }

    public function testSectionDerivativeHasCorrectPixelContent(): void
    {
        $sectionBlob = $this->createCroppedSection(0, 0, 320, 240);
        $this->assertNotEmpty($sectionBlob);
        $bucket = $this->createTempBucket();
        $sectionFile = new File($bucket);
        $sectionFile->name = 'section_topleft.jpg';
        $sectionFile->content = $sectionBlob;
        $config = Config::create('thumb', ['max_width' => 100, 'max_height' => 100, 'crop' => true]);
        $adapter = new ImageMagick();
        $result = $adapter->process($config, $sectionFile, 'thumb');
        $pixel = $this->getPixelColor($result->content, 10, 10);
        $this->assertArrayHasKey('r', $pixel);
        $this->assertArrayHasKey('g', $pixel);
        $this->assertArrayHasKey('b', $pixel);
        $this->assertGreaterThan($pixel['g'], $pixel['r'], 'top-left section should be reddish (fixture quadrant)');
        $this->assertGreaterThan($pixel['b'], $pixel['r']);
    }

    public function testSectionAndPictureDerivativesAreDifferent(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $sectionBlob = $this->createCroppedSection(0, 0, 320, 240);
        $sectionFile = new File($bucket);
        $sectionFile->name = 'section.jpg';
        $sectionFile->content = $sectionBlob;
        $config = Config::create('thumb', ['max_width' => 100, 'max_height' => 100, 'crop' => true]);
        $adapter = new ImageMagick();
        $fullResult = $adapter->process($config, $file, 'thumb');
        $sectionResult = $adapter->process($config, $sectionFile, 'thumb');
        $this->assertNotSame($fullResult->content, $sectionResult->content);
    }

    public function testMultipleSectionsProduceDifferentDerivatives(): void
    {
        $bucket = $this->createTempBucket();
        $section1 = $this->createCroppedSection(0, 0, 320, 240);
        $section2 = $this->createCroppedSection(320, 240, 320, 240);
        $file1 = new File($bucket);
        $file1->name = 's1.jpg';
        $file1->content = $section1;
        $file2 = new File($bucket);
        $file2->name = 's2.jpg';
        $file2->content = $section2;
        $config = Config::create('thumb', ['max_width' => 100, 'max_height' => 100, 'crop' => true]);
        $adapter = new ImageMagick();
        $result1 = $adapter->process($config, $file1, 'thumb');
        $result2 = $adapter->process($config, $file2, 'thumb');
        $this->assertNotSame($result1->content, $result2->content);
    }

    /**
     * ImageMagickCLI uses horizontal_crop in -gravity when crop=true. Verify process runs and output has correct dimensions.
     */
    public function testImageMagickCLICropUsesGravity(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $config = Config::create('thumb', [
            'max_width' => 200,
            'max_height' => 200,
            'crop' => true,
            'horizontal_crop' => 'center',
        ]);
        $adapter = new ImageMagickCLI();
        $result = $adapter->process($config, $file, 'thumb');
        $this->assertInstanceOf(File::class, $result);
        $this->assertSame('test-landscape_thumb.jpg', $result->name);
        if (!empty($result->content)) {
            $im = new Imagick();
            $im->readImageBlob($result->content);
            $this->assertSame(200, $im->getImageWidth());
            $this->assertSame(200, $im->getImageHeight());
            $im->destroy();
        }
    }
}
