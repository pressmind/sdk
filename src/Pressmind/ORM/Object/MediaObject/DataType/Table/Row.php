<?php


namespace Pressmind\ORM\Object\MediaObject\DataType\Table;


use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\MediaObject\DataType\Table\Row\Column;

/**
 * Class Row
 * @package Pressmind\ORM\Object\MediaObject\DataType\Table
 * @property integer $id
 * @property integer $id_table
 * @property integer $sort
 * @property Column[] $columns
 */
class Row extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_media_object_table_rows',
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
                ]
            ],
            'id_table' => [
                'title' => 'id_table',
                'name' => 'id_table',
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
                'index' => [
                    'id_table' => 'index'
                ]
            ],
            'sort' => [
                'title' => 'sort',
                'name' => 'sort',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => null
            ],
            'columns' => [
                'title' => 'columns',
                'name' => 'columns',
                'type' => 'relation',
                'required' => false,
                'filters' => null,
                'validators' => null,
                'relation' => [
                    'type' => 'hasMany',
                    'class' => '\\Pressmind\\ORM\\Object\\MediaObject\\DataType\\Table\\Row\\Column',
                    'related_id' => 'id_table_row',
                    'on_save_related_properties' => [
                        'id' => 'id_table_row',
                        'id_table' => 'id_table'
                    ],
                    'filters' => null
                ],
            ]

        ]
    ];
}
