<?php


namespace Pressmind\ORM\Object;

use DateTime;

/**
 * Class Log
 * @package Pressmind\ORM\Object
 * @property integer $id
 * @property DateTime $date
 * @property string $type
 * @property string $text
 * @property string $category
 * @property string $trace
 */
class Log extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => 'Log',
            'namespace' => '\Pressmind\ORM\Object',
        ],
        'database' => [
            'table_name' => 'pmt2core_logs',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'id',
                'name' => 'id',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                ],
            ],
            'date' => [
                'title' => 'date',
                'name' => 'date',
                'type' => 'datetime',
                'required' => true,
                'filters' => null,
                'validators' => null
            ],
            'type' => [
                'title' => 'type',
                'name' => 'type',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'inarray',
                        'params' => [
                            'DEBUG',
                            'INFO',
                            'WARNING',
                            'ERROR',
                            'FATAL'
                        ]
                    ]
                ]
            ],
            'text' => [
                'title' => 'text',
                'name' => 'text',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'category' => [
                'title' => 'category',
                'name' => 'category',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255
                    ]
                ]
            ],
            'trace' => [
                'title' => 'trace',
                'name' => 'trace',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ]
        ]
    ];
}