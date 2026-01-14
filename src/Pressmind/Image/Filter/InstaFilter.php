<?php

namespace Pressmind\Image\Filter;

use Imagick;
use ImagickPixel;
use Pressmind\Log\Writer;

/**
 * Class InstaFilter
 * 
 * Instagram-style image filters with various presets.
 * 
 * Available presets:
 * - vintage: Warm sepia tones, slightly desaturated, soft vignette
 * - vivid: Increased saturation and contrast, punchy colors
 * - warm: Golden toning, warmer colors (sunset look)
 * - cool: Blueish toning, cooler colors (nordic look)
 * - fade: Washed out colors, reduced contrast (retro film look)
 * 
 * @package Pressmind\Image\Filter
 */
class InstaFilter extends AbstractFilter
{
    /**
     * Default parameter values
     */
    private const DEFAULTS = [
        'preset' => 'vintage',
        'intensity' => 1.0
    ];

    /**
     * Preset configurations
     */
    private const PRESETS = [
        'vintage' => [
            'saturation' => 80,
            'brightness' => 105,
            'contrast' => 5,
            'tint' => ['r' => 255, 'g' => 240, 'b' => 200],
            'tint_amount' => 0.15,
            'vignette' => true,
            'gamma' => 1.1
        ],
        'vivid' => [
            'saturation' => 130,
            'brightness' => 100,
            'contrast' => 10,
            'tint' => null,
            'tint_amount' => 0,
            'vignette' => false,
            'gamma' => 0.95
        ],
        'warm' => [
            'saturation' => 105,
            'brightness' => 102,
            'contrast' => 3,
            'tint' => ['r' => 255, 'g' => 200, 'b' => 140],
            'tint_amount' => 0.12,
            'vignette' => false,
            'gamma' => 1.05
        ],
        'cool' => [
            'saturation' => 95,
            'brightness' => 100,
            'contrast' => 5,
            'tint' => ['r' => 180, 'g' => 210, 'b' => 255],
            'tint_amount' => 0.10,
            'vignette' => false,
            'gamma' => 1.0
        ],
        'fade' => [
            'saturation' => 70,
            'brightness' => 108,
            'contrast' => -10,
            'tint' => ['r' => 240, 'g' => 235, 'b' => 230],
            'tint_amount' => 0.08,
            'vignette' => true,
            'gamma' => 1.15
        ]
    ];

    /**
     * @inheritDoc
     */
    public function apply(Imagick $image, array $params): Imagick
    {
        $params = $this->mergeParams($params, self::DEFAULTS);
        
        $preset = $params['preset'];
        $intensity = $this->clamp($params['intensity'], 0.0, 1.0);
        
        if (!isset(self::PRESETS[$preset])) {
            Writer::write(
                'InstaFilter: Unknown preset "' . $preset . '", using vintage',
                Writer::OUTPUT_FILE,
                'image_processor',
                Writer::TYPE_WARNING
            );
            $preset = 'vintage';
        }
        
        $config = self::PRESETS[$preset];
        
        // Apply intensity scaling (blend with neutral values)
        $config = $this->applyIntensity($config, $intensity);
        
        // Apply modulation (brightness, saturation)
        $image->modulateImage(
            $config['brightness'],
            $config['saturation'],
            100 // Hue unchanged
        );
        
        // Apply contrast
        if ($config['contrast'] != 0) {
            $this->adjustContrast($image, $config['contrast']);
        }
        
        // Apply gamma correction
        if ($config['gamma'] != 1.0) {
            $image->gammaImage($config['gamma']);
        }
        
        // Apply color tint
        if ($config['tint'] !== null && $config['tint_amount'] > 0) {
            $this->applyTint($image, $config['tint'], $config['tint_amount']);
        }
        
        // Apply vignette
        if ($config['vignette']) {
            $this->applyVignette($image);
        }
        
        Writer::write(
            'InstaFilter: Applied preset "' . $preset . '" with intensity ' . $intensity,
            Writer::OUTPUT_FILE,
            'image_processor',
            Writer::TYPE_INFO
        );
        
        return $image;
    }

    /**
     * Scale preset values based on intensity
     * 
     * @param array $config Preset configuration
     * @param float $intensity Intensity factor (0.0 - 1.0)
     * @return array Adjusted configuration
     */
    private function applyIntensity(array $config, float $intensity): array
    {
        if ($intensity >= 1.0) {
            return $config;
        }
        
        // Blend saturation towards 100 (neutral)
        $config['saturation'] = 100 + (($config['saturation'] - 100) * $intensity);
        
        // Blend brightness towards 100 (neutral)
        $config['brightness'] = 100 + (($config['brightness'] - 100) * $intensity);
        
        // Scale contrast
        $config['contrast'] = $config['contrast'] * $intensity;
        
        // Blend gamma towards 1.0 (neutral)
        $config['gamma'] = 1.0 + (($config['gamma'] - 1.0) * $intensity);
        
        // Scale tint amount
        $config['tint_amount'] = $config['tint_amount'] * $intensity;
        
        // Disable vignette at low intensity
        if ($intensity < 0.5) {
            $config['vignette'] = false;
        }
        
        return $config;
    }

    /**
     * Adjust image contrast
     * 
     * @param Imagick $image
     * @param float $amount Positive for more contrast, negative for less
     */
    private function adjustContrast(Imagick $image, float $amount): void
    {
        $amount = (int) round($amount);
        if ($amount > 0) {
            for ($i = 0; $i < abs($amount); $i += 5) {
                $image->contrastImage(true);
            }
        } else {
            for ($i = 0; $i < abs($amount); $i += 5) {
                $image->contrastImage(false);
            }
        }
    }

    /**
     * Apply a color tint overlay
     * 
     * @param Imagick $image
     * @param array $color RGB color values
     * @param float $amount Tint amount (0.0 - 1.0)
     */
    private function applyTint(Imagick $image, array $color, float $amount): void
    {
        $width = $image->getImageWidth();
        $height = $image->getImageHeight();
        
        $overlay = new Imagick();
        $overlay->newImage($width, $height, new ImagickPixel(
            sprintf('rgb(%d,%d,%d)', $color['r'], $color['g'], $color['b'])
        ));
        $overlay->setImageFormat('png');
        
        // Set opacity for the overlay
        $overlay->evaluateImage(Imagick::EVALUATE_SET, $amount, Imagick::CHANNEL_ALPHA);
        
        $image->compositeImage($overlay, Imagick::COMPOSITE_OVERLAY, 0, 0);
        
        $overlay->destroy();
    }

    /**
     * Apply a vignette effect
     * 
     * @param Imagick $image
     */
    private function applyVignette(Imagick $image): void
    {
        $width = $image->getImageWidth();
        $height = $image->getImageHeight();
        
        // Create radial gradient for vignette
        $vignette = new Imagick();
        $vignette->newPseudoImage($width, $height, 'radial-gradient:white-black');
        
        // Soften the vignette
        $vignette->blurImage(0, (int) round($width * 0.1));
        
        // Apply vignette using multiply composite
        $image->compositeImage($vignette, Imagick::COMPOSITE_MULTIPLY, 0, 0);
        
        $vignette->destroy();
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'insta';
    }
}
