<?php


namespace Pressmind\Image\Processor;


class Config
{
    public $name;
    public $max_width;
    public $max_height;
    public $preserve_aspect_ratio;
    public $crop;
    public $horizontal_crop;
    public $vertical_crop;
    public $webp_quality;
    public $webp_create;
    
    /**
     * @var array|null Filter configurations for this derivative
     */
    public $filters;

    public function __construct()
    {

    }

    public static function create($name, $config_array)
    {
        $config = new self();
        $config->name = $name;
        $config->max_width = isset($config_array['max_width']) ? $config_array['max_width'] : null;
        $config->max_height = isset($config_array['max_height']) ? $config_array['max_height'] : null;
        $config->preserve_aspect_ratio = isset($config_array['preserve_aspect_ratio']) ? $config_array['preserve_aspect_ratio'] : null;
        $config->crop = isset($config_array['crop']) ? $config_array['crop'] : null;
        $config->horizontal_crop = isset($config_array['horizontal_crop']) ? $config_array['horizontal_crop'] : null;
        $config->vertical_crop = isset($config_array['vertical_crop']) ? $config_array['vertical_crop'] : null;
        $config->webp_quality = isset($config_array['webp_quality']) ? $config_array['webp_quality'] : null;
        $config->webp_create = isset($config_array['webp_create']) ? $config_array['webp_create'] : false;
        $config->filters = isset($config_array['filters']) ? $config_array['filters'] : null;
        return $config;
    }
}
