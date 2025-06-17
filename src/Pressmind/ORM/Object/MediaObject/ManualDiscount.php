<?php
namespace Pressmind\ORM\Object\MediaObject;
use DateTime;
use Pressmind\ORM\Object\AbstractObject;

/**
 * Class ManualDiscount
 * @package Pressmind\ORM\Object\MediaObject
 * @property string $id
 * @property integer $id_media_object
 * @property DateTime $travel_date_from
 * @property DateTime $travel_date_to
 * @property DateTime $booking_date_from
 * @property DateTime $booking_date_to
 * @property string $description
 * @property float $value
 * @property string $type // fixed_price | percent
 * @property string $agency
 */
class ManualDiscount extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_replace_into_on_create = true;

    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_media_object_manual_discounts',
            'primary_key' => 'id'
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
            'id_media_object' => [
                'title' => 'id_media_object',
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
                    'type' => 'index'
                ]
            ],
            'travel_date_from' => [
                'title' => 'travel_date_from',
                'name' => 'travel_date_from',
                'type' => 'datetime',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'travel_date_to' => [
                'title' => 'travel_date_to',
                'name' => 'travel_date_to',
                'type' => 'datetime',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'booking_date_from' => [
                'title' => 'booking_date_from',
                'name' => 'booking_date_from',
                'type' => 'datetime',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'booking_date_to' => [
                'title' => 'booking_date_to',
                'name' => 'booking_date_to',
                'type' => 'datetime',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'description' => [
                'title' => 'description',
                'name' => 'description',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ]
                ],
                'filters' => null
            ],
            'value' => [
                'title' => 'value',
                'name' => 'value',
                'type' => 'float',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => null
            ],
            'type' => [
                'title' => 'type',
                'name' => 'type',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'inarray',
                        'params' => ['fixed_price', 'percent'],
                    ]
                ],
                'filters' => null
            ],
            'agency' => [
                'title' => 'agency',
                'name' => 'agency',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ]
                ],
                'filters' => null
            ]

        ]
    ];
}
