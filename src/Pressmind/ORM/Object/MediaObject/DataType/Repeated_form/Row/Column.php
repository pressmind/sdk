<?php


namespace Pressmind\ORM\Object\MediaObject\DataType\Repeated_form\Row;


use Pressmind\ORM\Object\AbstractObject;

/**
 * Class Column
 * @package Pressmind\ORM\Object\MediaObject\DataType\Repeated_form\Row
 * @property integer $id
 * @property integer $id_repeated_form
 * @property integer $id_repeated_form_row
 * @property integer $sort
 * @property string $var_name
 * @property string $title
 * @property string $datatype
 * @property string $value_string
 */
class Column extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_media_object_repeated_form_row_columns',
            'primary_key' => 'id',
            'order_columns' => ['sort' => 'ASC']
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
            'id_repeated_form' => [
                'title' => 'id_repeated_form',
                'name' => 'id_repeated_form',
                'type' => 'integer',
                'required' => false,
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
                'index' => [
                    'id_repeated_form' => 'index'
                ]
            ],
            'id_repeated_form_row' => [
                'title' => 'id_repeated_form_row',
                'name' => 'id_repeated_form_row',
                'type' => 'integer',
                'required' => false,
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
                'index' => [
                    'id_repeated_form_row' => 'index'
                ]
            ],
            'sort' => [
                'title' => 'sort',
                'name' => 'sort',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
            'var_name'  => [
                'title' => 'var_name',
                'name' => 'var_name',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'title'  => [
                'title' => 'title',
                'name' => 'title',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'datatype'  => [
                'title' => 'datatype',
                'name' => 'datatype',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'value_string'  => [
                'title' => 'value_string',
                'name' => 'value_string',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
        ]
    ];
}
