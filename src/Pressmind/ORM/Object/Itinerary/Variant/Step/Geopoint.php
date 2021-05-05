<?php


namespace Pressmind\ORM\Object\Itinerary\Variant\Step;


use Pressmind\ORM\Object\AbstractObject;

/**
 * Class Geopoint
 * @package Pressmind\ORM\Object\Itinerary\Variant\Step
 * @property integer $id
 * @property integer $id_step
 * @property float $lat
 * @property float $lng
 * @property string $address
 * @property string $title
 */
class Geopoint extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_itinerary_step_geopoints',
            'primary_key' => 'id'
        ],
        'properties' => [
            'id' => [
                'title' => 'id',
                'name' => 'id',
                'type' => 'integer',
                'required' => true,
                'validators' => null,
                'filters' => null
            ],
            'id_step' => [
                'title' => 'id_step',
                'name' => 'id_step',
                'type' => 'integer',
                'required' => false,
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
                'index' => [
                    'id_step' => 'index'
                ],
                'filters' => null
            ],
            'lat' => [
                'title' => 'lat',
                'name' => 'lat',
                'type' => 'float',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'lng' => [
                'title' => 'lng',
                'name' => 'lng',
                'type' => 'float',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'address' => [
                'title' => 'address',
                'name' => 'address',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'title' => [
                'title' => 'title',
                'name' => 'title',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ]
        ]
    ];
}
