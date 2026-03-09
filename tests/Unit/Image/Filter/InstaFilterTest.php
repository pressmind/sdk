<?php

namespace Pressmind\Tests\Unit\Image\Filter;

use Imagick;
use Pressmind\Image\Filter\InstaFilter;
use Pressmind\Tests\Unit\AbstractTestCase;

class InstaFilterTest extends AbstractTestCase
{
    public function testGetNameReturnsInsta(): void
    {
        $filter = new InstaFilter();
        $this->assertSame('insta', $filter->getName());
    }

    /**
     * @requires extension imagick
     */
    public function testApplyVintagePreset(): void
    {
        $filter = new InstaFilter();
        $image = new Imagick();
        $image->newImage(50, 50, new \ImagickPixel('white'));
        $result = $filter->apply($image, ['preset' => 'vintage']);
        $this->assertSame($image, $result);
        $this->assertSame(50, $image->getImageWidth());
        $image->destroy();
    }

    /**
     * @requires extension imagick
     */
    public function testApplyVividPreset(): void
    {
        $filter = new InstaFilter();
        $image = new Imagick();
        $image->newImage(50, 50, new \ImagickPixel('white'));
        $result = $filter->apply($image, ['preset' => 'vivid']);
        $this->assertSame($image, $result);
        $image->destroy();
    }

    /**
     * @requires extension imagick
     */
    public function testApplyWarmPreset(): void
    {
        $filter = new InstaFilter();
        $image = new Imagick();
        $image->newImage(50, 50, new \ImagickPixel('white'));
        $result = $filter->apply($image, ['preset' => 'warm']);
        $this->assertSame($image, $result);
        $image->destroy();
    }

    /**
     * @requires extension imagick
     */
    public function testApplyCoolPreset(): void
    {
        $filter = new InstaFilter();
        $image = new Imagick();
        $image->newImage(50, 50, new \ImagickPixel('white'));
        $result = $filter->apply($image, ['preset' => 'cool']);
        $this->assertSame($image, $result);
        $image->destroy();
    }

    /**
     * @requires extension imagick
     */
    public function testApplyFadePreset(): void
    {
        $filter = new InstaFilter();
        $image = new Imagick();
        $image->newImage(50, 50, new \ImagickPixel('white'));
        $result = $filter->apply($image, ['preset' => 'fade']);
        $this->assertSame($image, $result);
        $image->destroy();
    }

    /**
     * @requires extension imagick
     */
    public function testApplyUnknownPresetFallsBackToVintage(): void
    {
        $filter = new InstaFilter();
        $image = new Imagick();
        $image->newImage(50, 50, new \ImagickPixel('white'));
        $result = $filter->apply($image, ['preset' => 'unknown_preset_name']);
        $this->assertSame($image, $result);
        $image->destroy();
    }

    /**
     * @requires extension imagick
     */
    public function testApplyIntensityZeroReturnsNearNeutral(): void
    {
        $filter = new InstaFilter();
        $image = new Imagick();
        $image->newImage(50, 50, new \ImagickPixel('white'));
        $result = $filter->apply($image, ['preset' => 'vintage', 'intensity' => 0.0]);
        $this->assertSame($image, $result);
        $image->destroy();
    }

    /**
     * @requires extension imagick
     */
    public function testApplyIntensityHalfReducesEffect(): void
    {
        $filter = new InstaFilter();
        $image = new Imagick();
        $image->newImage(50, 50, new \ImagickPixel('white'));
        $result = $filter->apply($image, ['preset' => 'vivid', 'intensity' => 0.5]);
        $this->assertSame($image, $result);
        $image->destroy();
    }

    /**
     * @requires extension imagick
     */
    public function testVignetteDisabledAtLowIntensity(): void
    {
        $filter = new InstaFilter();
        $image = new Imagick();
        $image->newImage(50, 50, new \ImagickPixel('white'));
        $result = $filter->apply($image, ['preset' => 'vintage', 'intensity' => 0.3]);
        $this->assertSame($image, $result);
        $image->destroy();
    }
}
