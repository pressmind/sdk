<?php


namespace Pressmind\ORM\Object\MediaObject\DataType\Table\Row;


use Pressmind\ORM\Object\AbstractObject;

/**
 * Class Column
 * @package Pressmind\ORM\Object\MediaObject\DataType\Table\Row
 * @property integer $id
 * @property integer $id_table
 * @property integer $id_table_row
 * @property integer $sort
 * @property integer $colspan
 * @property integer $width
 * @property integer $height
 * @property string $style
 * @property string $text
 */
class Column extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => 'Column',
            'namespace' => '\Pressmind\ORM\MediaObject\DataType\Table\Row',
        ],
        'database' => [
            'table_name' => 'pmt2core_media_object_table_row_columns',
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
            'id_table' => [
                'title' => 'id_table',
                'name' => 'id_table',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'id_table_row' => [
                'title' => 'id_table_row',
                'name' => 'id_table_row',
                'type' => 'integer',
                'required' => false,
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
            'colspan'  => [
                'title' => 'colspan',
                'name' => 'colspan',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'style'  => [
                'title' => 'style',
                'name' => 'style',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'width'  => [
                'title' => 'width',
                'name' => 'width',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'height'  => [
                'title' => 'height',
                'name' => 'height',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'text'  => [
                'title' => 'text',
                'name' => 'text',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ]
        ]
    ];
}
