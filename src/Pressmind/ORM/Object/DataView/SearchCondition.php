<?php


namespace Pressmind\ORM\Object\DataView;


use Pressmind\ORM\Object\AbstractObject;

/**
 * Class SearchCondition
 * @package Pressmind\ORM\Object\DataView
 * @property integer $id
 * @property integer $id_data_view
 * @property string $class_name
 * @property string $values
 */
class SearchCondition extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => 'SearchCondition',
            'namespace' => '\Pressmind\ORM\Object\DataView',
        ],
        'database' => [
            'table_name' => 'pmt2core_data_view_search_conditions',
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
                        'params' => 20,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
            ],
            'id_data_view' => [
                'title' => 'id_data_view',
                'name' => 'id_data_view',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 20,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
            ],
            'class_name' => [
                'title' => 'class_name',
                'name' => 'class_name',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => null
            ],
            'values' => [
                'title' => 'values',
                'name' => 'values',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => null
            ]
        ]
    ];
}