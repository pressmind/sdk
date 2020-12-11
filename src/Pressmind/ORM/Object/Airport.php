<?php

namespace Pressmind\ORM\Object;

/**
 * Class Airport
 * @property integer $id
 * @property string $name
 * @property string $city
 * @property string $country
 * @property string $iata
 * @property string $icao
 * @property float $latitude
 * @property float $longitude
 * @property float $altitude_feet
 * @property string $timezone
 * @property string $dst
 * @property string $source
 */
class Airport extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_airports',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'Id',
                'name' => 'id',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                ],
                'filters' => NULL,
            ],
            'name' => [
                'title' => 'Name',
                'name' => 'name',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => NULL,
            ],
            'city' => [
                'title' => 'City',
                'name' => 'city',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
            'filters' => NULL,
            ],
            'country' => [
                'title' => 'Country',
                'name' => 'country',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => NULL,
            ],
            'iata' => [
                'title' => 'Iata',
                'name' => 'iata',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 3,
                    ],
                ],
                'filters' => NULL,
            ],
            'icao' => [
                'title' => 'Icao',
                'name' => 'icao',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 4,
                    ],
                ],
                'filters' => NULL,
            ],
            'latitude' => [
                'title' => 'Latitude',
                'name' => 'latitude',
                'type' => 'float',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'longitude' => [
                'title' => 'Longitude',
                'name' => 'longitude',
                'type' => 'float',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'altitude_feet' => [
                'title' => 'Altitude_feet',
                'name' => 'altitude_feet',
                'type' => 'float',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'timezone' => [
                'title' => 'Timezone',
                'name' => 'timezone',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => NULL,
            ],
            'dst' => [
                'title' => 'Dst',
                'name' => 'dst',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 1,
                    ],
                ],
                'filters' => NULL,
            ],
            'source' => [
                'title' => 'Source',
                'name' => 'source',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => NULL,
            ],
        ],
    ];
}
