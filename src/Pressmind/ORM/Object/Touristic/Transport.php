<?php

namespace Pressmind\ORM\Object\Touristic;
use Pressmind\ORM\Object\AbstractObject;
use DateTime;
use Pressmind\System\I18n;

/**
 * Class Transport
 * @property string $id
 * @property string $id_date
 * @property integer $id_media_object
 * @property string $id_booking_package
 * @property integer $id_early_bird_discount_group
 * @property string $code
 * @property string $description
 * @property string $type
 * @property integer $way
 * @property float $price
 * @property integer $order
 * @property integer $state
 * @property string $code_ibe
 * @property integer $auto_book
 * @property integer $required
 * @property string $required_group
 * @property string $transport_group
 * @property string $description_long
 * @property string $id_starting_point
 * @property DateTime $transport_date_from
 * @property DateTime $transport_date_to
 * @property integer $age_from
 * @property integer $age_to
 * @property EarlyBirdDiscountGroup $early_bird_discount_group
 */
class Transport extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_transports',
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
            'id_date' => [
                'title' => 'Id_date',
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
            'id_early_bird_discount_group' => [
                'title' => 'id_early_bird_discount_group',
                'name' => 'id_early_bird_discount_group',
                'type' => 'string',
                'required' => false,
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
            'description' => [
                'title' => 'Description',
                'name' => 'description',
                'type' => 'string',
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
                        'params' => 5,
                    ],
                ],
                'filters' => NULL,
            ],
            'way' => [
                'title' => 'Way',
                'name' => 'way',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 4,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
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
            'order' => [
                'title' => 'Order',
                'name' => 'order',
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
            'state' => [
                'title' => 'State',
                'name' => 'state',
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
            'auto_book' => [
                'title' => 'Auto_book',
                'name' => 'auto_book',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 4,
                    ],
                ],
                'filters' => NULL,
            ],
            'required' => [
                'title' => 'Required',
                'name' => 'required',
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
            'required_group' => [
                'title' => 'Required_group',
                'name' => 'required_group',
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
            'transport_group' => [
                'title' => 'Transport_group',
                'name' => 'transport_group',
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
            'description_long' => [
                'title' => 'Description_long',
                'name' => 'description_long',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'id_starting_point' => [
                'title' => 'Id_starting_point',
                'name' => 'id_starting_point',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ],
                ],
                'filters' => NULL,
            ],
            'transport_date_from' => [
                'title' => 'Transport_date_from',
                'name' => 'transport_date_from',
                'type' => 'datetime',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'transport_date_to' => [
                'title' => 'Transport_date_to',
                'name' => 'transport_date_to',
                'type' => 'datetime',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'age_from' => [
                'title' => 'Age from',
                'name' => 'age_from',
                'type' => 'integer',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'age_to' => [
                'title' => 'Age to',
                'name' => 'age_to',
                'type' => 'integer',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'early_bird_discount_group' => [
                'title' => 'Early Bird Discount',
                'name' => 'early_bird_discount_group',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasOne',
                    'related_id' => 'id_early_bird_discount_group',
                    'class' => EarlyBirdDiscountGroup::class,
                ],
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'starting_points' => [
                'title' => 'Starting Points',
                'name' => 'starting_points',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasOne',
                    'related_id' => 'id_starting_point',
                    'class' => Startingpoint::class,
                ],
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ]
        ]
    );

    public function mapTypeToString() {
        $mapping = [
            'BUS' => 'Busreise',
            'PKW' => 'Eigenanreise',
            'FLUG' => 'Flugreise',
            'SCHIFF' => 'Schiffsreise'
        ];
        return I18n::translate($mapping[$this->type]);
    }
}
