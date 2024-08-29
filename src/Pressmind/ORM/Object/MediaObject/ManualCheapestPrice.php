<?php


namespace Pressmind\ORM\Object\MediaObject;


use DateTime;
use Pressmind\ORM\Object\AbstractObject;

/**
 * Class ManualCheapestPrice
 * @package Pressmind\ORM\Object\MediaObject
 * @property integer $id
 * @property integer $id_media_object
 * @property integer $id_my_content
 * @property string $import_id
 * @property string $editor_link
 * @property string $detail_link
 * @property string $detail_name
 * @property string $detail_code
 * @property string $detail_checksum
 * @property boolean $is_intern
 * @property DateTime $last_update
 */
class ManualCheapestPrice extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_replace_into_on_create = true;

    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_media_object_manual_cheapest_prices',
            'primary_key' => 'id'
        ],
        'properties' => [
            'id' => [
                'title' => 'id',
                'name' => 'id',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => null
            ],
            'id_media_object' => [
                'title' => 'id_media_object+',
                'name' => 'id_media_object',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => null,
                'index' => [
                    'id_media_object' => 'index'
                ]
            ],
            'valid_from' => [
                'title' => 'valid_from',
                'name' => 'valid_from',
                'type' => 'datetime',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'valid_to' => [
                'title' => 'valid_to',
                'name' => 'valid_to',
                'type' => 'datetime',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'duration' => [
                'title' => 'duration',
                'name' => 'duration',
                'type' => 'integer',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'occupancy_min' => [
                'title' => 'occupancy_min',
                'name' => 'occupancy_min',
                'type' => 'integer',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
             'occupancy' => [
                'title' => 'occupancy',
                'name' => 'occupancy',
                'type' => 'integer',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'occupancy_max' => [
                'title' => 'occupancy_max',
                'name' => 'occupancy_max',
                'type' => 'integer',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
             'description_1' => [
                'title' => 'description_1',
                'name' => 'description_1',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'description_2' => [
                'title' => 'description_2',
                'name' => 'description_2',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'price' => [
                'title' => 'price',
                'name' => 'price',
                'type' => 'float',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'price_pseudo' => [
                'title' => 'price_pseudo',
                'name' => 'price_pseudo',
                'type' => 'float',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
        ]
    ];
}
