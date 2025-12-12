<?php

namespace Pressmind\ORM\Object\Touristic\EarlyPaymentDiscountGroup;

use DateTime;
use Pressmind\ORM\Object\AbstractObject;

/**
 * Class Item
 * @property string $id
 * @property string $id_early_payment_discount_group
 * @property DateTime $travel_date_from
 * @property DateTime $travel_date_to
 * @property DateTime $booking_date_from
 * @property DateTime $booking_date_to
 * @property int $booking_days_before_departure
 * @property int $min_stay_nights
 * @property float $discount_value
 * @property string $type
 * @property boolean $round
 * @property string $origin
 * @property string $agency
 * @property string $name
 * @property DateTime $have_to_pay_before_date
 * @property int $have_to_pay_after_booking_date_days
 * @property string $room_condition_code_ibe
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
            'table_name' => 'pmt2core_touristic_early_payment_discount_group_item',
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
            'id_early_payment_discount_group' => [
                'title' => 'id_early_payment_discount_group',
                'name' => 'id_early_payment_discount_group',
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
                    'id_early_payment_discount_group' => 'index'
                ]
            ],
            'travel_date_from' => [
                'title' => 'Travel_date_from',
                'name' => 'travel_date_from',
                'type' => 'datetime',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'travel_date_to' => [
                'title' => 'Travel_date_to',
                'name' => 'travel_date_to',
                'type' => 'datetime',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'booking_date_from' => [
                'title' => 'Booking_date_from',
                'name' => 'booking_date_from',
                'type' => 'datetime',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'booking_date_to' => [
                'title' => 'Booking_date_to',
                'name' => 'booking_date_to',
                'type' => 'datetime',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'booking_days_before_departure' => [
                'title' => 'booking_days_before_departure',
                'name' => 'booking_days_before_departure',
                'type' => 'integer',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'min_stay_nights' => [
                'title' => 'min_stay_nights',
                'name' => 'min_stay_nights',
                'type' => 'integer',
                'required' => false,
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
            'origin' => [
                'title' => 'origin',
                'name' => 'origin',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'agency' => [
                'title' => 'agency',
                'name' => 'agency',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'name' => [
                'title' => 'name',
                'name' => 'name',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'room_condition_code_ibe' => [
                'title' => 'room_condition_code_ibe',
                'name' => 'room_condition_code_ibe',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'have_to_pay_before_date' => [
                'title' => 'have_to_pay_before_date',
                'name' => 'have_to_pay_before_date',
                'type' => 'datetime',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'have_to_pay_after_booking_date_days' => [
                'title' => 'have_to_pay_after_booking_date_days',
                'name' => 'have_to_pay_after_booking_date_days',
                'type' => 'int',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
        ]
    );
}
