<?php

namespace Pressmind\Tests\Integration\Image;

use Imagick;
use Pressmind\Image\Processor\Adapter\ImageMagick;
use Pressmind\Image\Processor\Adapter\WebPicture;
use Pressmind\Image\Processor\Config;
use Pressmind\Storage\File;
use Pressmind\Tests\Fixtures\Images\ImageFixtureTrait;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * E2E tests simulating the full derivative creation pipeline with local temp storage.
 *
 * @requires extension imagick
 */
class ImageProcessorE2ETest extends AbstractTestCase
{
    use ImageFixtureTrait;

    public function testFullPipelineFromRawImageToDerivatives(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $file->save();

        $derivativesConfig = [
            'thumb' => Config::create('thumb', ['max_width' => 200, 'max_height' => 200, 'crop' => true]),
            'teaser' => Config::create('teaser', ['max_width' => 400, 'max_height' => 300, 'crop' => false, 'preserve_aspect_ratio' => true]),
        ];
        $adapter = new ImageMagick();
        $created = [];
        foreach ($derivativesConfig as $name => $config) {
            $result = $adapter->process($config, $file, $name);
            $this->assertInstanceOf(File::class, $result);
            $result->save();
            $created[$name] = $result;
        }

        $this->assertCount(2, $created);
        foreach ($created as $name => $derivative) {
            $this->assertTrue($derivative->exists(), "Derivative {$name} should exist");
            $im = new Imagick();
            $im->readImageBlob($derivative->content);
            $this->assertGreaterThan(0, $im->getImageWidth());
            $this->assertGreaterThan(0, $im->getImageHeight());
            $im->destroy();
        }
    }

    public function testDerivativeNamingConvention(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $config = Config::create('thumb', ['max_width' => 100, 'max_height' => 100, 'crop' => true]);
        $adapter = new ImageMagick();
        $result = $adapter->process($config, $file, 'thumb');
        $pathInfo = pathinfo($file->name);
        $expectedName = $pathInfo['filename'] . '_thumb.' . $pathInfo['extension'];
        $this->assertSame($expectedName, $result->name);
    }

    public function testWebpDerivativesCreatedWhenConfigured(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $config = Config::create('thumb', ['max_width' => 100, 'max_height' => 100, 'crop' => true]);
        $imAdapter = new ImageMagick();
        $derivative = $imAdapter->process($config, $file, 'thumb');
        $derivative->save();

        $webpConfig = Config::create('webp', ['webp_create' => true, 'webp_quality' => 80]);
        $webpAdapter = new WebPicture();
        $webpFile = $webpAdapter->process($webpConfig, $derivative, 'webp');
        $this->assertNotNull($webpFile);
        $this->assertStringEndsWith('.webp', $webpFile->name);
        $webpFile->save();
        $this->assertTrue($webpFile->exists());
    }

    public function testFilterChainAppliedInDerivatives(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $config = Config::create('thumb', [
            'max_width' => 100,
            'max_height' => 100,
            'crop' => true,
            'filters' => [['class' => \Pressmind\Image\Filter\GrayscaleFilter::class, 'params' => []]],
        ]);
        $adapter = new ImageMagick();
        $result = $adapter->process($config, $file, 'thumb');
        $im = new Imagick();
        $im->readImageBlob($result->content);
        $this->assertSame(\Imagick::COLORSPACE_GRAY, $im->getImageColorspace());
        $im->destroy();
    }

    public function testFullPipelineWithCroppedSection(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);

        $sectionBlob = $this->createCroppedSection(0, 0, 320, 240);
        $this->assertNotEmpty($sectionBlob);
        $sectionFile = new File($bucket);
        $sectionFile->name = 'test-landscape_section.jpg';
        $sectionFile->content = $sectionBlob;

        $config = Config::create('thumb', ['max_width' => 100, 'max_height' => 100, 'crop' => true]);
        $adapter = new ImageMagick();
        $fullResult = $adapter->process($config, $file, 'thumb');
        $sectionResult = $adapter->process($config, $sectionFile, 'thumb');

        $this->assertNotSame($fullResult->content, $sectionResult->content, 'Section derivative should differ from full image derivative');
    }

    public function testMultipleDerivativeConfigsProduceCorrectOutputs(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);

        $configs = [
            'thumbnail' => Config::create('thumbnail', ['max_width' => 200, 'max_height' => 200, 'crop' => true]),
            'teaser' => Config::create('teaser', ['max_width' => 800, 'max_height' => 600, 'crop' => false, 'preserve_aspect_ratio' => true]),
            'detail' => Config::create('detail', ['max_width' => 1200, 'max_height' => 900, 'crop' => false, 'preserve_aspect_ratio' => true]),
        ];
        $adapter = new ImageMagick();

        foreach ($configs as $name => $config) {
            $result = $adapter->process($config, $file, $config->name);
            $im = new Imagick();
            $im->readImageBlob($result->content);
            $w = $im->getImageWidth();
            $h = $im->getImageHeight();
            $im->destroy();

            if ($name === 'thumbnail') {
                $this->assertSame(200, $w, 'thumbnail width');
                $this->assertSame(200, $h, 'thumbnail height');
            } elseif ($name === 'teaser') {
                $this->assertSame(800, $w, 'teaser: 640x480 fits 800x600 with same aspect ratio');
                $this->assertSame(600, $h, 'teaser height');
            } elseif ($name === 'detail') {
                $this->assertSame(1200, $w, 'detail: 640x480 fits 1200x900 with same aspect ratio');
                $this->assertSame(900, $h, 'detail height');
            } else {
                $this->assertLessThanOrEqual($config->max_width, $w);
                $this->assertLessThanOrEqual($config->max_height, $h);
            }
        }
    }
}
