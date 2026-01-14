<?php

namespace Pressmind\Image\Filter;

use Imagick;
use Pressmind\Log\Writer;

/**
 * Class FilterChain
 * 
 * Manages and executes a chain of image filters.
 * Filters are applied in the order they are added.
 * 
 * @package Pressmind\Image\Filter
 */
class FilterChain
{
    /**
     * @var array Array of filter configurations [['filter' => FilterInterface, 'params' => array], ...]
     */
    private $_filters = [];

    /**
     * Add a filter to the chain
     * 
     * @param FilterInterface $filter The filter instance
     * @param array $params Parameters for this filter
     * @return self
     */
    public function addFilter(FilterInterface $filter, array $params = []): self
    {
        $this->_filters[] = [
            'filter' => $filter,
            'params' => $params
        ];
        return $this;
    }

    /**
     * Process the image through all filters in the chain
     * 
     * @param Imagick $image The image to process
     * @return Imagick The processed image
     */
    public function process(Imagick $image): Imagick
    {
        foreach ($this->_filters as $filterConfig) {
            $filter = $filterConfig['filter'];
            $params = $filterConfig['params'];
            
            Writer::write(
                'Applying filter: ' . $filter->getName(),
                Writer::OUTPUT_FILE,
                'image_processor',
                Writer::TYPE_INFO
            );
            
            $image = $filter->apply($image, $params);
        }
        
        return $image;
    }

    /**
     * Get the number of filters in the chain
     * 
     * @return int
     */
    public function count(): int
    {
        return count($this->_filters);
    }

    /**
     * Create a FilterChain from configuration array
     * 
     * @param array $filtersConfig Array of filter configurations from pm-config
     * @return self
     */
    public static function createFromConfig(array $filtersConfig): self
    {
        $chain = new self();
        
        foreach ($filtersConfig as $filterConfig) {
            if (empty($filterConfig['class'])) {
                continue;
            }
            
            $filterClass = $filterConfig['class'];
            $params = $filterConfig['params'] ?? [];
            
            if (!class_exists($filterClass)) {
                Writer::write(
                    'Filter class not found: ' . $filterClass,
                    Writer::OUTPUT_FILE,
                    'image_processor',
                    Writer::TYPE_WARNING
                );
                continue;
            }
            
            $filter = new $filterClass();
            
            if (!($filter instanceof FilterInterface)) {
                Writer::write(
                    'Filter class does not implement FilterInterface: ' . $filterClass,
                    Writer::OUTPUT_FILE,
                    'image_processor',
                    Writer::TYPE_WARNING
                );
                continue;
            }
            
            $chain->addFilter($filter, $params);
        }
        
        return $chain;
    }
}
