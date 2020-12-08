<?php


namespace Pressmind\ORM\Object\Touristic;


use Pressmind\ORM\Object\AbstractObject;

/**
 * Class Base
 * @package Pressmind\ORM\Object\Touristic
 * @property string $id
 * @property integer $id_media_object
 * @property integer $id_season_set
 * @property boolean $booking_on_request
 * @property integer $id_ibe_type
 */
class Base extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = [
        'class' => [
            'name' => 'Base',
            'namespace' => '\Pressmind\ORM\Object\Touristic'
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_base',
            'primary_key' => 'id'
        ],
        'properties' => [
            'id' => [
                'title' => 'ID',
                'name' => 'id',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ],
                ],
                'filters' => NULL
            ],
            'id_media_object' => [
                'title' => 'ID MediaObject',
                'name' => 'id_media_object',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                ],
                'filters' => NULL
            ],
            'id_season_set' => [
                'title' => 'ID Season Set',
                'name' => 'id_season_set',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                ],
                'filters' => NULL
            ],
            'booking_on_request' => [
                'title' => 'Booking on request',
                'name' => 'booking_on_request',
                'type' => 'boolean',
                'required' => false,
                'filters' => NULL
            ],
            'id_ibe_type' => [
                'title' => 'ID IBE type',
                'name' => 'id_ibe_type',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                ],
                'filters' => NULL
            ]
        ]
    ];
}
