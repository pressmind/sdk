<?php

namespace Pressmind\ORM\Object\Touristic\Booking;

use DateTime;
use Pressmind\ORM\Object\AbstractObject;

/**
 * Class Earlybird
 * @property integer $id
 * @property integer $id_booking_package
 * @property integer $id_media_object
 * @property DateTime $travel_date_from
 * @property DateTime $travel_date_to
 * @property DateTime $booking_date_from
 * @property DateTime $booking_date_to
 * @property float $discount_value
 * @property string $type
 * @property boolean $round
 * @property boolean $early_payer
 */
class Earlybird extends AbstractObject
{

    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_booking_earlybirds',
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
            'id_booking_package' => [
                'title' => 'Id_booking_package',
                'name' => 'id_booking_package',
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
                    'id_booking_package' => 'index'
                ]
            ],
            'id_media_object' => [
                'title' => 'Id_media_object',
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
                'filters' => NULL,
                'index' => [
                    'id_media_object' => 'index'
                ]
            ],
            'travel_date_from' => [
                'title' => 'Travel_date_from',
                'name' => 'travel_date_from',
                'type' => 'datetime',
                'required' => true,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'travel_date_to' => [
                'title' => 'Travel_date_to',
                'name' => 'travel_date_to',
                'type' => 'datetime',
                'required' => true,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'booking_date_from' => [
                'title' => 'Booking_date_from',
                'name' => 'booking_date_from',
                'type' => 'datetime',
                'required' => true,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'booking_date_to' => [
                'title' => 'Booking_date_to',
                'name' => 'booking_date_to',
                'type' => 'datetime',
                'required' => true,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'discount_value' => [
                'title' => 'Discount_value',
                'name' => 'discount_value',
                'type' => 'float',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'type' => [
                'title' => 'Type',
                'name' => 'type',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 12,
                    ],
                ],
                'filters' => NULL,
            ],
            'round' => [
                'title' => 'Round',
                'name' => 'round',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => NULL,
            ],
            'early_payer' => [
                'title' => 'Early_payer',
                'name' => 'early_payer',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => NULL,
            ],
        ]
    );
}
