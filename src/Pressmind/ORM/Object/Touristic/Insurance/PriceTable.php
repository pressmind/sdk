<?php

namespace Pressmind\ORM\Object\Touristic\Insurance;

use DateTime;
use Pressmind\ORM\Object\AbstractObject;

/**
 * Class PriceTable
 * @property string $id
 * @property string $id_insurance
 * @property string $code
 * @property float $price
 * @property string $unit
 * @property string $price_type
 * @property boolean $family_insurance
 * @property boolean $pair_insurance
 * @property integer $age_to
 * @property integer $age_from
 * @property DateTime $travel_date_to
 * @property DateTime $travel_date_from
 * @property DateTime $booking_date_to
 * @property DateTime $booking_date_from
 * @property float $travel_price_min
 * @property float $travel_price_max
 * @property string $code_ibe
 * @property integer $travel_duration_from
 * @property integer $travel_duration_to
 * @property integer $pax_min
 * @property integer $pax_max
 */
class PriceTable extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_replace_into_on_create = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_insurances_price_tables',
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
            'id_insurance' => [
                'title' => 'Id_insurance',
                'name' => 'id_insurance',
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
                    'id_insurance' => 'index'
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
            'price' => [
                'title' => 'Price',
                'name' => 'price',
                'type' => 'float',
                'required' => true,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'unit' => [
                'title' => 'unit',
                'name' => 'unit',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'inarray',
                        'params' => [
                            'per_person',
                            'per_unit',
                        ],
                    ],
                ],
                'filters' => NULL,
            ],
            'price_type' => [
                'title' => 'Price_type',
                'name' => 'price_type',
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
            'family_insurance' => [
                'title' => 'Family_insurance',
                'name' => 'family_insurance',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => NULL,
            ],
            'pair_insurance' => [
                'title' => 'pair_insurance',
                'name' => 'pair_insurance',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => NULL,
            ],
            'age_to' => [
                'title' => 'Age_to',
                'name' => 'age_to',
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
            'age_from' => [
                'title' => 'Age_from',
                'name' => 'age_from',
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
            'travel_date_to' => [
                'title' => 'Travel_date_to',
                'name' => 'travel_date_to',
                'type' => 'datetime',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'travel_date_from' => [
                'title' => 'Travel_date_from',
                'name' => 'travel_date_from',
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
            'booking_date_from' => [
                'title' => 'Booking_date_from',
                'name' => 'booking_date_from',
                'type' => 'datetime',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'travel_price_min' => [
                'title' => 'Travel_price_min',
                'name' => 'travel_price_min',
                'type' => 'float',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'travel_price_max' => [
                'title' => 'Travel_price_max',
                'name' => 'travel_price_max',
                'type' => 'float',
                'required' => true,
                'validators' => NULL,
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
            'travel_duration_from' => [
                'title' => 'Travel_duration_from',
                'name' => 'travel_duration_from',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
            ],
            'travel_duration_to' => [
                'title' => 'Travel_duration_to',
                'name' => 'travel_duration_to',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
            ],
            'pax_min' => [
                'title' => 'Pax_min',
                'name' => 'pax_min',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
            ],
            'pax_max' => [
                'title' => 'Pax_max',
                'name' => 'pax_max',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
            ],
        ]
    );
}
