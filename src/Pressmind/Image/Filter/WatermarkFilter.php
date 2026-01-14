<?php

namespace Pressmind\Image\Filter;

use Imagick;
use ImagickException;
use Pressmind\Log\Writer;

/**
 * Class WatermarkFilter
 * 
 * Applies a watermark image to the processed derivative.
 * Supports positioning, relative sizing, and opacity control.
 * 
 * @package Pressmind\Image\Filter
 */
class WatermarkFilter extends AbstractFilter
{
    /**
     * Default parameter values
     */
    private const DEFAULTS = [
        'position' => 'bottom-right',
        'size' => 10,
        'margin_x' => 10,
        'margin_y' => 10,
        'opacity' => 1.0
    ];

    /**
     * @inheritDoc
     */
    public function apply(Imagick $image, array $params): Imagick
    {
        $params = $this->mergeParams($params, self::DEFAULTS);
        
        if (empty($params['image'])) {
            Writer::write(
                'WatermarkFilter: No watermark image specified',
                Writer::OUTPUT_FILE,
                'image_processor',
                Writer::TYPE_WARNING
            );
            return $image;
        }
        
        $watermarkPath = $params['image'];
        
        if (!file_exists($watermarkPath)) {
            Writer::write(
                'WatermarkFilter: Watermark file not found: ' . $watermarkPath,
                Writer::OUTPUT_FILE,
                'image_processor',
                Writer::TYPE_WARNING
            );
            return $image;
        }
        
        try {
            $watermark = new Imagick($watermarkPath);
            
            // Get image dimensions
            $imageWidth = $image->getImageWidth();
            $imageHeight = $image->getImageHeight();
            
            // Calculate watermark size (percentage of image width)
            $sizePercent = $this->clamp($params['size'], 1, 100);
            $targetWidth = $this->percentageOf($imageWidth, $sizePercent);
            
            // Scale watermark proportionally
            $watermarkWidth = $watermark->getImageWidth();
            $watermarkHeight = $watermark->getImageHeight();
            $scaleFactor = $targetWidth / $watermarkWidth;
            $newWidth = (int) round($watermarkWidth * $scaleFactor);
            $newHeight = (int) round($watermarkHeight * $scaleFactor);
            
            $watermark->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1);
            
            // Apply opacity
            $opacity = $this->clamp($params['opacity'], 0.0, 1.0);
            if ($opacity < 1.0) {
                $watermark->evaluateImage(Imagick::EVALUATE_MULTIPLY, $opacity, Imagick::CHANNEL_ALPHA);
            }
            
            // Calculate position
            $marginX = (int) $params['margin_x'];
            $marginY = (int) $params['margin_y'];
            
            list($posX, $posY) = $this->calculatePosition(
                $params['position'],
                $imageWidth,
                $imageHeight,
                $newWidth,
                $newHeight,
                $marginX,
                $marginY
            );
            
            // Composite watermark onto image
            $image->compositeImage($watermark, Imagick::COMPOSITE_OVER, $posX, $posY);
            
            $watermark->destroy();
            
            Writer::write(
                'WatermarkFilter: Applied watermark at position ' . $params['position'],
                Writer::OUTPUT_FILE,
                'image_processor',
                Writer::TYPE_INFO
            );
            
        } catch (ImagickException $e) {
            Writer::write(
                'WatermarkFilter: Error applying watermark: ' . $e->getMessage(),
                Writer::OUTPUT_FILE,
                'image_processor',
                Writer::TYPE_ERROR
            );
        }
        
        return $image;
    }

    /**
     * Calculate the x,y position for the watermark
     * 
     * @param string $position Position identifier
     * @param int $imageWidth Image width
     * @param int $imageHeight Image height
     * @param int $watermarkWidth Watermark width
     * @param int $watermarkHeight Watermark height
     * @param int $marginX Horizontal margin in pixels
     * @param int $marginY Vertical margin in pixels
     * @return array [x, y] position
     */
    private function calculatePosition(
        string $position,
        int $imageWidth,
        int $imageHeight,
        int $watermarkWidth,
        int $watermarkHeight,
        int $marginX,
        int $marginY
    ): array {
        switch ($position) {
            case 'top-left':
                return [$marginX, $marginY];
                
            case 'top-right':
                return [$imageWidth - $watermarkWidth - $marginX, $marginY];
                
            case 'bottom-left':
                return [$marginX, $imageHeight - $watermarkHeight - $marginY];
                
            case 'bottom-right':
                return [
                    $imageWidth - $watermarkWidth - $marginX,
                    $imageHeight - $watermarkHeight - $marginY
                ];
                
            case 'center':
                return [
                    (int) (($imageWidth - $watermarkWidth) / 2),
                    (int) (($imageHeight - $watermarkHeight) / 2)
                ];
                
            default:
                // Default to bottom-right
                return [
                    $imageWidth - $watermarkWidth - $marginX,
                    $imageHeight - $watermarkHeight - $marginY
                ];
        }
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'watermark';
    }
}
