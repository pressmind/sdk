<?php


namespace Pressmind\ORM\Object\Itinerary\Step;


use Pressmind\ORM\Object\AbstractObject;

/**
 * Class Port
 * @package Pressmind\ORM\Object\Itinerary\Variant\Step
 * @property integer $id
 * @property integer $id_step
 * @property integer $id_port
 * @property string $departure_time
 * @property string $arrival_time
 * @property integer $day
 */
class Port extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_itinerary_step_ports',
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
            'id_port' => [
                'title' => 'id_port',
                'name' => 'id_port',
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
            'departure_time' => [
                'title' => 'departure_time',
                'name' => 'departure_time',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'arrival_time' => [
                'title' => 'arrival_time',
                'name' => 'arrival_time',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'day' => [
                'title' => 'day',
                'name' => 'day',
                'type' => 'integer',
                'required' => false,
                'validators' => null,
                'filters' => null
            ]
        ]
    ];
}
