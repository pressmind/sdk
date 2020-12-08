<?php


namespace Pressmind\ORM\Object\MediaObject\DataType\Key_value\Row;


use DateTime;
use Pressmind\ORM\Object\AbstractObject;

/**
 * Class Column
 * @package Pressmind\ORM\Object\MediaObject\DataType\Key_value\Row
 * @property integer $id
 * @property integer id_key_value
 * @property integer $id_key_value_row
 * @property integer $sort
 * @property string $var_name
 * @property string $title
 * @property string $datatype
 * @property integer $value_string
 * @property string $value_integer
 * @property string $value_float
 * @property DateTime $value_datetime
 */
class Column extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => 'Column',
            'namespace' => '\Pressmind\ORM\MediaObject\DataType\Key_value\Row',
        ],
        'database' => [
            'table_name' => 'pmt2core_media_object_key_value_row_columns',
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
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'id_key_value_row' => [
                'title' => 'id_key_value_row',
                'name' => 'id_key_value_row',
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
            'value_float'  => [
                'title' => 'value_float',
                'name' => 'value_float',
                'type' => 'float',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'value_integer'  => [
                'title' => 'value_integer',
                'name' => 'value_integer',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'value_datetime'  => [
                'title' => 'value_datetime',
                'name' => 'value_datetime',
                'type' => 'datetime',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
        ]
    ];
}
