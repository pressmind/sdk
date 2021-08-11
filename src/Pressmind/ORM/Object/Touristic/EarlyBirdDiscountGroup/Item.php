<?php

namespace Pressmind\ORM\Object\Touristic\EarlyBirdDiscountGroup;

use DateTime;
use Pressmind\ORM\Object\AbstractObject;

/**
 * Class Item
 * @property string $id
 * @property string $id_early_bird_discount_group
 * @property DateTime $travel_date_from
 * @property DateTime $travel_date_to
 * @property DateTime $booking_date_from
 * @property DateTime $booking_date_to
 * @property float $discount_value
 * @property string $type
 * @property boolean $round
 * @property boolean $early_payer
 */
class Item extends AbstractObject
{

    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_replace_into_on_create = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_early_bird_discount_group_item',
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
            'id_early_bird_discount_group' => [
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
                'index' => [
                    'id_early_bird_discount_group' => 'index'
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
                'required' => true,
                'validators' => [
                    [
                        'name' => 'inarray',
                        'params' => [
                            'P',
                            'F'
                        ],
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
