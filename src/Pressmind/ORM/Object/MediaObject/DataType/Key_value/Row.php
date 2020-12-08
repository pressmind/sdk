<?php


namespace Pressmind\ORM\Object\MediaObject\DataType\Key_value;


use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\MediaObject\DataType\Key_value\Row\Column;

/**
 * Class Row
 * @package Pressmind\ORM\Object\MediaObject\DataType\Key_value
 * @property integer $id
 * @property integer $id_key_value
 * @property integer $sort
 * @property Column[] $columns
 */
class Row extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => 'Row',
            'namespace' => '\Pressmind\ORM\MediaObject\DataType\Key_value',
        ],
        'database' => [
            'table_name' => 'pmt2core_media_object_key_value_rows',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'id',
                'name' => 'id',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
            'id_key_value' => [
                'title' => 'id_key_value',
                'name' => 'id_key_value',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
            'sort' => [
                'title' => 'sort',
                'name' => 'sort',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => null,
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
                    'class' => '\\Pressmind\\ORM\\Object\\MediaObject\\DataType\\Key_value\\Row\\Column',
                    'related_id' => 'id_key_value_row',
                    'on_save_related_properties' => [
                        'id' => 'id_key_value_row',
                        'id_key_value' => 'id_key_value'
                    ],
                    'filters' => null,
                ],
            ]

        ]
    ];
}
