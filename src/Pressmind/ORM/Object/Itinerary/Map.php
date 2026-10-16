<?php

namespace Pressmind\ORM\Object\Itinerary;

use Pressmind\ORM\Object\AbstractObject;

/**
 * Separate from MediaObject: itineraries are imported before the media object itself.
 * @property int $id Media object ID
 * @property array|null $geojson Complete overview FeatureCollection from Webcore
 * @property array|null $search_geometry Derived, two-dimensional points and GPX tracks
 */
class Map extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;
    protected $_replace_into_on_create = true;
    protected $_disable_cache_permanently = true;

    protected $_definitions = [
        'class' => ['name' => self::class],
        'database' => ['table_name' => 'pmt2core_itinerary_maps', 'primary_key' => 'id'],
        'properties' => [
            'id' => [
                'title' => 'id', 'name' => 'id', 'type' => 'integer',
                'required' => true, 'validators' => null, 'filters' => null,
            ],
            'geojson' => [
                'title' => 'geojson', 'name' => 'geojson', 'type' => 'json',
                'required' => false, 'validators' => null, 'filters' => null,
            ],
            'search_geometry' => [
                'title' => 'search_geometry', 'name' => 'search_geometry', 'type' => 'json',
                'required' => false, 'validators' => null, 'filters' => null,
            ],
        ],
    ];
}
