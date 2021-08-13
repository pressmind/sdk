<?php


namespace Pressmind\ORM\Object\Touristic\Option\Discount;


use DateTime;
use Pressmind\ORM\Object\AbstractObject;

/**
 * Class Scale
 * @package Pressmind\ORM\Object\Touristic\Option\Discount\Scale
 * @property string $id
 * @property string $id_touristic_option_discount
 * @property string $name
 * @property string $type
 * @property float $value
 * @property integer $occupancy
 * @property integer $pax
 * @property integer $discounted_person
 * @property integer $age_from
 * @property integer $age_to
 * @property DateTime $valid_from
 * @property DateTime $valid_to
 * @property string $frequency
 */
class Scale extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_replace_into_on_create = true;

    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_option_discount_scales',
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
            'id_touristic_option_discount' => [
                'title' => 'id_touristic_option_discount',
                'name' => 'id_touristic_option_discount',
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
                    'id_touristic_option_discount' => 'index'
                ]
            ],
            'name' => [
                'title' => 'name',
                'name' => 'name',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
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
            'value' => [
                'title' => 'Value',
                'name' => 'value',
                'type' => 'float',
                'required' => true,
                'validators' => null,
                'filters' => NULL,
            ],
            'occupancy' => [
                'title' => 'Occupancy',
                'name' => 'occupancy',
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
            'pax' => [
                'title' => 'Pax',
                'name' => 'pax',
                'type' => 'integer',
                'required' => false,
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
            'discounted_person' => [
                'title' => 'Discounted Person',
                'name' => 'discounted_person',
                'type' => 'integer',
                'required' => false,
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
            'age_from' => [
                'title' => 'Age From',
                'name' => 'age_from',
                'type' => 'integer',
                'required' => false,
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
                'required' => false,
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
                'required' => false,
                'validators' => null,
                'filters' => NULL
            ],
            'valid_to' => [
                'title' => 'Valid From',
                'name' => 'valid_from',
                'type' => 'datetime',
                'required' => false,
                'validators' => null,
                'filters' => NULL
            ],
            'frequency' => [
                'title' => 'Frequency',
                'name' => 'frequency',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'inarray',
                        'params' => [
                            'E'
                        ],
                    ],
                ],
                'filters' => NULL,
            ]
        ]
    ];
}
