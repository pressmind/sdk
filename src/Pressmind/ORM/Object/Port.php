<?php

namespace Pressmind\ORM\Object;

/**
 * Class Port
 * @property integer $id
 * @property string $name
 * @property string $code
 * @property boolean $active
 * @property string $description
 */
class Port extends AbstractObject
{

    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = [
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_ports',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'id',
                'name' => 'id',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                ],
                'filters' => NULL,
            ],
            'active' => [
                'title' => 'active',
                'name' => 'active',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => null,
            ],
            'code' => [
                'title' => 'code',
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
            'name' => [
                'title' => 'Name',
                'name' => 'name',
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
                'title' => 'description',
                'name' => 'description',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
        ],
    ];

    public static $run_time_cache = [];

}
