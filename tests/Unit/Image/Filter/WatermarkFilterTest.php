<?php

namespace Pressmind\Tests\Unit\Image\Filter;

use Imagick;
use Pressmind\Image\Filter\WatermarkFilter;
use Pressmind\Tests\Fixtures\Images\ImageFixtureTrait;
use Pressmind\Tests\Unit\AbstractTestCase;

class WatermarkFilterTest extends AbstractTestCase
{
    use ImageFixtureTrait;

    public function testGetNameReturnsWatermark(): void
    {
        $filter = new WatermarkFilter();
        $this->assertSame('watermark', $filter->getName());
    }

    /**
     * @requires extension imagick
     */
    public function testApplyWithoutImageParamReturnsUnchanged(): void
    {
        $filter = new WatermarkFilter();
        $image = new Imagick();
        $image->newImage(100, 100, new \ImagickPixel('white'));
        $w = $image->getImageWidth();
        $h = $image->getImageHeight();
        $result = $filter->apply($image, []);
        $this->assertSame($image, $result);
        $this->assertSame($w, $image->getImageWidth());
        $this->assertSame($h, $image->getImageHeight());
        $image->destroy();
    }

    /**
     * @requires extension imagick
     */
    public function testApplyWithNonExistentFileReturnsUnchanged(): void
    {
        $filter = new WatermarkFilter();
        $image = new Imagick();
        $image->newImage(100, 100, new \ImagickPixel('white'));
        $w = $image->getImageWidth();
        $h = $image->getImageHeight();
        $result = $filter->apply($image, ['image' => '/nonexistent/watermark.png']);
        $this->assertSame($image, $result);
        $this->assertSame($w, $image->getImageWidth());
        $this->assertSame($h, $image->getImageHeight());
        $image->destroy();
    }

    /**
     * @requires extension imagick
     */
    public function testApplyWithValidWatermark(): void
    {
        $filter = new WatermarkFilter();
        $image = new Imagick();
        $image->newImage(200, 200, new \ImagickPixel('white'));
        $watermarkPath = $this->getWatermarkPath();
        $result = $filter->apply($image, ['image' => $watermarkPath]);
        $this->assertSame($image, $result);
        $this->assertSame(200, $image->getImageWidth());
        $this->assertSame(200, $image->getImageHeight());
        $image->destroy();
    }

    /**
     * @requires extension imagick
     */
    public function testApplyPositionTopLeft(): void
    {
        $filter = new WatermarkFilter();
        $image = new Imagick();
        $image->newImage(100, 100, new \ImagickPixel('white'));
        $result = $filter->apply($image, ['image' => $this->getWatermarkPath(), 'position' => 'top-left', 'margin_x' => 0, 'margin_y' => 0]);
        $this->assertSame($image, $result);
        $image->destroy();
    }

    /**
     * @requires extension imagick
     */
    public function testApplyPositionTopRight(): void
    {
        $filter = new WatermarkFilter();
        $image = new Imagick();
        $image->newImage(100, 100, new \ImagickPixel('white'));
        $result = $filter->apply($image, ['image' => $this->getWatermarkPath(), 'position' => 'top-right']);
        $this->assertSame($image, $result);
        $image->destroy();
    }

    /**
     * @requires extension imagick
     */
    public function testApplyPositionBottomLeft(): void
    {
        $filter = new WatermarkFilter();
        $image = new Imagick();
        $image->newImage(100, 100, new \ImagickPixel('white'));
        $result = $filter->apply($image, ['image' => $this->getWatermarkPath(), 'position' => 'bottom-left']);
        $this->assertSame($image, $result);
        $image->destroy();
    }

    /**
     * @requires extension imagick
     */
    public function testApplyPositionBottomRight(): void
    {
        $filter = new WatermarkFilter();
        $image = new Imagick();
        $image->newImage(100, 100, new \ImagickPixel('white'));
        $result = $filter->apply($image, ['image' => $this->getWatermarkPath(), 'position' => 'bottom-right']);
        $this->assertSame($image, $result);
        $image->destroy();
    }

    /**
     * @requires extension imagick
     */
    public function testApplyPositionCenter(): void
    {
        $filter = new WatermarkFilter();
        $image = new Imagick();
        $image->newImage(100, 100, new \ImagickPixel('white'));
        $result = $filter->apply($image, ['image' => $this->getWatermarkPath(), 'position' => 'center']);
        $this->assertSame($image, $result);
        $image->destroy();
    }

    /**
     * @requires extension imagick
     */
    public function testApplyPositionDefaultFallback(): void
    {
        $filter = new WatermarkFilter();
        $image = new Imagick();
        $image->newImage(100, 100, new \ImagickPixel('white'));
        $result = $filter->apply($image, ['image' => $this->getWatermarkPath(), 'position' => 'invalid-position']);
        $this->assertSame($image, $result);
        $image->destroy();
    }

    /**
     * @requires extension imagick
     */
    public function testOpacityIsApplied(): void
    {
        $filter = new WatermarkFilter();
        $image = new Imagick();
        $image->newImage(100, 100, new \ImagickPixel('white'));
        $result = $filter->apply($image, ['image' => $this->getWatermarkPath(), 'opacity' => 0.5]);
        $this->assertSame($image, $result);
        $image->destroy();
    }
}
