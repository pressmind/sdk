<?php

namespace Pressmind\Tests\Integration\Image;

use Imagick;
use Pressmind\Image\Filter\FilterChain;
use Pressmind\Image\Filter\GrayscaleFilter;
use Pressmind\Image\Filter\InstaFilter;
use Pressmind\Image\Filter\WatermarkFilter;
use Pressmind\Tests\Fixtures\Images\ImageFixtureTrait;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Full filter pipeline integration tests with real fixture image.
 *
 * @requires extension imagick
 */
class FilterPipelineTest extends AbstractTestCase
{
    use ImageFixtureTrait;

    public function testGrayscaleFilterProducesGrayscaleImage(): void
    {
        $blob = file_get_contents($this->getTestImagePath());
        $this->assertNotFalse($blob);
        $im = new Imagick();
        $im->readImageBlob($blob);
        $chain = new FilterChain();
        $chain->addFilter(new GrayscaleFilter(), []);
        $result = $chain->process($im);
        $this->assertSame(\Imagick::COLORSPACE_GRAY, $result->getImageColorspace());
        $im->destroy();
        if ($result !== $im) {
            $result->destroy();
        }
    }

    public function testWatermarkFilterCompositsOverlay(): void
    {
        $blob = file_get_contents($this->getTestImagePath());
        $this->assertNotFalse($blob);
        $im = new Imagick();
        $im->readImageBlob($blob);
        $watermarkPath = $this->getWatermarkPath();
        $chain = new FilterChain();
        $chain->addFilter(new WatermarkFilter(), [
            'watermark_path' => $watermarkPath,
            'position' => 'bottom-right',
            'opacity' => 0.5,
        ]);
        $result = $chain->process($im);
        $this->assertInstanceOf(Imagick::class, $result);
        $this->assertGreaterThan(0, strlen($result->getImageBlob()));
        $im->destroy();
        if ($result !== $im) {
            $result->destroy();
        }
    }

    public function testInstaVintagePresetModifiesImage(): void
    {
        $blob = file_get_contents($this->getTestImagePath());
        $this->assertNotFalse($blob);
        $im = new Imagick();
        $im->readImageBlob($blob);
        $originalBlob = $im->getImageBlob();
        $chain = new FilterChain();
        $chain->addFilter(new InstaFilter(), ['preset' => 'vintage', 'intensity' => 1.0]);
        $result = $chain->process($im);
        $resultBlob = $result->getImageBlob();
        $this->assertNotSame($originalBlob, $resultBlob);
        $im->destroy();
        if ($result !== $im) {
            $result->destroy();
        }
    }

    public function testFilterChainAppliesMultipleFilters(): void
    {
        $blob = file_get_contents($this->getTestImagePath());
        $this->assertNotFalse($blob);
        $im = new Imagick();
        $im->readImageBlob($blob);
        $chain = new FilterChain();
        $chain->addFilter(new GrayscaleFilter(), []);
        $chain->addFilter(new InstaFilter(), ['preset' => 'vintage', 'intensity' => 0.5]);
        $result = $chain->process($im);
        $this->assertSame(\Imagick::COLORSPACE_GRAY, $result->getImageColorspace());
        $im->destroy();
        if ($result !== $im) {
            $result->destroy();
        }
    }
}
