<?php


namespace Pressmind\ORM\Object;

use Pressmind\ORM\Object\DataView\SearchCondition;

/**
 * Class DataView
 * @package Pressmind\ORM\Object
 * @property integer $id
 * @property boolean $active
 * @property string $name
 * @property SearchCondition[] $search_conditions
 */
class DataView extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_data_views',
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
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
            ],
            'active' => [
                'title' => 'active',
                'name' => 'active',
                'type' => 'boolean',
                'required' => true,
                'filters' => null,
                'validators' => null
            ],
            'name' => [
                'title' => 'name',
                'name' => 'name',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ]
                ]
            ],
            'search_conditions' => [
                'title' => 'search_conditions',
                'name' => 'search_conditions',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_data_view',
                    'class' => SearchCondition::class,
                    'filters' => null
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ]
        ]
    ];
}
