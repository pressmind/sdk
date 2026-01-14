<?php

namespace Pressmind\Image\Filter;

use Imagick;

/**
 * Class AbstractFilter
 * 
 * Base class for image filters providing common helper methods.
 * 
 * @package Pressmind\Image\Filter
 */
abstract class AbstractFilter implements FilterInterface
{
    /**
     * Merge provided params with default values
     * 
     * @param array $params User-provided parameters
     * @param array $defaults Default parameter values
     * @return array Merged parameters
     */
    protected function mergeParams(array $params, array $defaults): array
    {
        return array_merge($defaults, $params);
    }

    /**
     * Clamp a value between min and max
     * 
     * @param float $value The value to clamp
     * @param float $min Minimum value
     * @param float $max Maximum value
     * @return float Clamped value
     */
    protected function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    /**
     * Calculate percentage of a dimension
     * 
     * @param int $dimension The dimension (width or height)
     * @param float $percentage The percentage (0-100)
     * @return int Calculated value in pixels
     */
    protected function percentageOf(int $dimension, float $percentage): int
    {
        return (int) round($dimension * ($percentage / 100));
    }
}
