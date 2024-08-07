<?php

namespace Pressmind\ORM\Object\Touristic\Startingpoint;

use Pressmind\ORM\Object\AbstractObject;
use \DateTime;
use Pressmind\ORM\Object\Touristic\Startingpoint\Option\ZipRange;

/**
 * Class Option
 * @property string $id
 * @property string $id_startingpoint
 * @property string $zip
 * @property string $code
 * @property string $name
 * @property float $price
 * @property float $base_price
 * @property string $text
 * @property DateTime $start_time
 * @property boolean $with_start_time
 * @property string $city
 * @property string $street
 * @property string $code_ibe
 * @property float $lat
 * @property float $lon
 * @property boolean $entry
 * @property boolean $exit
 * @property DateTime $exit_time
 * @property integer $exit_time_offset
 * @property integer $start_time_offset
 * @property boolean $with_exit_time
 * @property string $ibe_clients
 * @property boolean $is_pickup_service
 * @property ZipRange[] $zip_ranges
 * @property string $zip_validity_area
 * @property string $rail
 * @property string $transportation
 * @property integer $order
 * @property boolean $extended_price_scale
 * @property string $pickup_service_street
 * @property string $pickup_service_house_number
 * @property boolean $price_per_day
 */
class Option extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_replace_into_on_create = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_startingpoint_options',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'Id',
                'name' => 'id',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ],
                ],
                'filters' => NULL,
            ],
            'id_startingpoint' => [
                'title' => 'Id_startingpoint',
                'name' => 'id_startingpoint',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ],
                ],
                'filters' => NULL,
                'index' => [
                    'id_starting_point' => 'index'
                ]
            ],
            'zip' => [
                'title' => 'Zip',
                'name' => 'zip',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 5,
                    ],
                ],
                'filters' => NULL,
                'index' => [
                    'zip' => 'index'
                ]
            ],
            'code' => [
                'title' => 'Code',
                'name' => 'code',
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
            'name' => [
                'title' => 'Name',
                'name' => 'name',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'price' => [
                'title' => 'Price',
                'name' => 'price',
                'type' => 'float',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'base_price' => [
                'title' => 'base_price',
                'name' => 'base_price',
                'type' => 'float',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'text' => [
                'title' => 'Text',
                'name' => 'text',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'start_time' => [
                'title' => 'Start_time',
                'name' => 'start_time',
                'type' => 'time',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'with_start_time' => [
                'title' => 'With_start_time',
                'name' => 'with_start_time',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
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
            'street' => [
                'title' => 'Street',
                'name' => 'street',
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
            'code_ibe' => [
                'title' => 'Code_ibe',
                'name' => 'code_ibe',
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
            'lat' => [
                'title' => 'Lat',
                'name' => 'lat',
                'type' => 'float',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'lon' => [
                'title' => 'Lon',
                'name' => 'lon',
                'type' => 'float',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'entry' => [
                'title' => 'Entry',
                'name' => 'entry',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => NULL,
            ],
            'exit' => [
                'title' => 'Exit',
                'name' => 'exit',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => NULL,
            ],
            'exit_time' => [
                'title' => 'Exit_time',
                'name' => 'exit_time',
                'type' => 'time',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'exit_time_offset' => [
                'title' => 'Exit_time_offset',
                'name' => 'exit_time_offset',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ],
                ],
                'filters' => NULL,
            ],
            'start_time_offset' => [
                'title' => 'Start_time_offset',
                'name' => 'start_time_offset',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ],
                ],
                'filters' => NULL,
            ],
            'with_exit_time' => [
                'title' => 'With_exit_time',
                'name' => 'with_exit_time',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => NULL,
            ],
            'ibe_clients' => [
                'title' => 'Ibe_clients',
                'name' => 'ibe_clients',
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
            'is_pickup_service' => [
                'title' => 'is_pickup_service',
                'name' => 'is_pickup_service',
                'type' => 'boolean',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'zip_ranges' => [
                'title' => 'zip_ranges',
                'name' => 'zip_ranges',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_option',
                    'class' => ZipRange::class,
                ],
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'zip_validity_area' => [
                'title' => 'zip_validity_area',
                'name' => 'zip_validity_area',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => NULL,
            ],
            'rail' => [
                'title' => 'rail',
                'name' => 'rail',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => NULL,
            ],
            'transportation' => [
                'title' => 'transportation',
                'name' => 'transportation',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => NULL,
            ],
            'order' => [
                'title' => 'order',
                'name' => 'order',
                'type' => 'integer',
                'required' => false,
                'validators' => null,
                'filters' => NULL,
            ],
            'extended_price_scale' => [
                'title' => 'extended_price_scale',
                'name' => 'extended_price_scale',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => NULL,
            ],
            'pickup_service_street' => [
                'title' => 'pickup_service_street',
                'name' => 'pickup_service_street',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'pickup_service_house_number' => [
                'title' => 'pickup_service_house_number',
                'name' => 'pickup_service_house_number',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'price_per_day' => [
                'title' => 'price_per_day',
                'name' => 'price_per_day',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => NULL,
            ],
        ]
    );

    public static $run_time_cache = [];

    /**
     * Cached static shorthand for getById
     * @param $id
     * @return Option|null
     * @throws \Exception
     */
    public static function getById($id){
        if(isset(self::$run_time_cache[$id])){
            return self::$run_time_cache[$id];
        }
        $option = self::listOne('id = "'.$id.'"');
        self::$run_time_cache[$id] = $id;
        return $option;
    }
}
