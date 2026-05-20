<?php


namespace Pressmind\ORM\Object\MediaObject\DataType\Repeated_form;


use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\MediaObject\DataType\Repeated_form\Row\Column;

/**
 * Class Row
 * @package Pressmind\ORM\Object\MediaObject\DataType\Repeated_form
 * @property integer $id
 * @property integer $id_repeated_form
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
            'table_name' => 'pmt2core_media_object_repeated_form_rows',
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
                    'id_repeated_form' => 'index'
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
            'columns' => [
                'title' => 'columns',
                'name' => 'columns',
                'type' => 'relation',
                'required' => false,
                'filters' => null,
                'validators' => null,
                'relation' => [
                    'type' => 'hasMany',
                    'class' => '\\Pressmind\\ORM\\Object\\MediaObject\\DataType\\Repeated_form\\Row\\Column',
                    'related_id' => 'id_repeated_form_row',
                    'on_save_related_properties' => [
                        'id' => 'id_repeated_form_row',
                        'id_repeated_form' => 'id_repeated_form'
                    ],
                    'filters' => null,
                ],
            ]
        ]
    ];
}
