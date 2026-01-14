<?php

namespace Pressmind\Image\Filter;

use Imagick;

/**
 * Class GrayscaleFilter
 * 
 * Converts the image to grayscale.
 * 
 * @package Pressmind\Image\Filter
 */
class GrayscaleFilter extends AbstractFilter
{
    /**
     * @inheritDoc
     */
    public function apply(Imagick $image, array $params): Imagick
    {
        $image->setImageColorspace(Imagick::COLORSPACE_GRAY);
        
        return $image;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'grayscale';
    }
}
