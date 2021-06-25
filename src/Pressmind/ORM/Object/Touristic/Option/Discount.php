<?php


namespace Pressmind\ORM\Object\Touristic\Option;


use Pressmind\ORM\Object\AbstractObject;

class Discount extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_option_discounts',
            'primary_key' => 'id',
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
                'filters' => NULL,
            ],
            'id_option' => [
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
                    'id_option' => 'index'
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
            'value' => [
                'title' => 'Value',
                'name' => 'value',
                'type' => 'float',
                'required' => true,
                'validators' => null,
                'filters' => NULL,
            ],
            'age_from' => [
                'title' => 'Age From',
                'name' => 'age_from',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 3,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL
            ],
            'age_to' => [
                'title' => 'Age To',
                'name' => 'age_to',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 3,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL
            ],
            'valid_from' => [
                'title' => 'Valid From',
                'name' => 'valid_from',
                'type' => 'datetime',
                'required' => true,
                'validators' => null,
                'filters' => NULL
            ],
            'valid_to' => [
                'title' => 'Valid From',
                'name' => 'valid_from',
                'type' => 'datetime',
                'required' => true,
                'validators' => null,
                'filters' => NULL
            ],
        ]
    ];
}
