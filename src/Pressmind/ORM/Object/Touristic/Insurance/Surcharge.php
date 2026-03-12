<?php

namespace Pressmind\ORM\Object\Touristic\Insurance;

use Pressmind\ORM\Object\AbstractObject;

/**
 * Class Surcharge
 * @package Pressmind\ORM\Object\Touristic\Insurance
 * @property string $id
 * @property string $id_insurance
 * @property string $code
 * @property integer $duration_min
 * @property integer $duration_max
 * @property string $unit
 * @property float $value
 */
class Surcharge extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_replace_into_on_create = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_insurance_surcharges',
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
                        'params' => 36,
                    ],
                ],
                'filters' => NULL,
            ],
            'id_insurance' => [
                'title' => 'Insurance ID',
                'name' => 'id_insurance',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 36,
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
            'duration_min' => [
                'title' => 'Duration_min',
                'name' => 'duration_min',
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
            'duration_max' => [
                'title' => 'Duration_max',
                'name' => 'duration_max',
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
            'unit' => [
                'title' => 'Unit',
                'name' => 'unit',
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
            'value' => [
                'title' => 'Value',
                'name' => 'value',
                'type' => 'float',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
        ]
    );
}
