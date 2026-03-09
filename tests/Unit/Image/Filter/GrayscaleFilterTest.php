<?php

namespace Pressmind\Tests\Unit\Image\Filter;

use Imagick;
use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\Image\Filter\GrayscaleFilter;

class GrayscaleFilterTest extends AbstractTestCase
{
    public function testGetNameReturnsGrayscale(): void
    {
        $filter = new GrayscaleFilter();
        $this->assertSame('grayscale', $filter->getName());
    }

    /**
     * @requires extension imagick
     */
    public function testApplyChangesColorspace(): void
    {
        $filter = new GrayscaleFilter();
        $image = new Imagick();
        $image->newPseudoImage(10, 10, 'canvas:red');
        $this->assertNotSame(Imagick::COLORSPACE_GRAY, $image->getImageColorspace());
        $result = $filter->apply($image, []);
        $this->assertSame($image, $result);
        $this->assertSame(Imagick::COLORSPACE_GRAY, $image->getImageColorspace());
        $image->destroy();
    }
}
