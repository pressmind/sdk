<?php

namespace Pressmind\ORM\Object\Touristic\Date;

use Pressmind\ORM\Object\AbstractObject;

/**
 * Class LoyaltyPoints
 * Loyalty points rule of a date. The field names are the JSON names of the Kuschick import.
 * @property string $id
 * @property string $id_date
 * @property string $id_booking_package
 * @property integer $id_media_object
 * @property string $recipient A = registrant, T = participant
 * @property string $calculation U = per amount of revenue, F = fixed
 * @property integer $points
 * @property float $amount empty for calculation F
 */
class LoyaltyPoints extends AbstractObject
{
    const CALCULATION_PER_AMOUNT = 'U';
    const CALCULATION_FIXED = 'F';

    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_date_loyalty_points',
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
            'id_date' => [
                'title' => 'id_date',
                'name' => 'id_date',
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
                    'id_date' => 'index'
                ]
            ],
            'recipient' => [
                'title' => 'recipient',
                'name' => 'recipient',
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
            'calculation' => [
                'title' => 'calculation',
                'name' => 'calculation',
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
            'points' => [
                'title' => 'points',
                'name' => 'points',
                'type' => 'integer',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'amount' => [
                'title' => 'amount',
                'name' => 'amount',
                'type' => 'float',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
        ]
    );
}
