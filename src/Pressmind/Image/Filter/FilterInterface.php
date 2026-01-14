<?php

namespace Pressmind\Image\Filter;

use Imagick;

/**
 * Interface FilterInterface
 * 
 * Defines the contract for image filters that can be applied to derivatives.
 * Filters are applied after the image has been resized/cropped.
 * 
 * @package Pressmind\Image\Filter
 */
interface FilterInterface
{
    /**
     * Apply the filter to an image
     * 
     * @param Imagick $image The image to apply the filter to
     * @param array $params Filter-specific parameters from configuration
     * @return Imagick The modified image
     */
    public function apply(Imagick $image, array $params): Imagick;

    /**
     * Get the unique name of this filter
     * 
     * @return string
     */
    public function getName(): string;
}
