<?php

namespace Pressmind\ORM\Object\CategoryTree;

use Pressmind\ORM\Object\AbstractObject;

/**
 * Class CategoryTreeItem
 * @property string $id
 * @property string $id_parent
 * @property string $id_tree
 * @property string $name
 * @property string $code
 * @property string $id_media_object
 * @property string $dynamic_values
 * @property integer $sort
 * @property Item[] $children
 */
class Item extends AbstractObject
{

    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = [
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_category_tree_items',
            'primary_key' => 'id',
            'order_columns' => ['sort' => 'ASC']
        ],
        'properties' => [
            'id' => [
                'title' => 'Id',
                'name' => 'id',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => NULL,
            ],
            'id_parent' => [
                'title' => 'Id_parent',
                'name' => 'id_parent',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => NULL,
                'index' => [
                    'id_parent' => 'index'
                ],
            ],
            'id_tree' => [
                'title' => 'Id_tree',
                'name' => 'id_tree',
                'type' => 'integer',
                'required' => true,
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
                'filters' => NULL,
                'index' => [
                    'id_tree' => 'index'
                ],
            ],
            'name' => [
                'title' => 'Name',
                'name' => 'name',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => NULL,
            ],
            'code' => [
                'title' => 'Code',
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
            'id_media_object' => [
                'title' => 'Id_media_object',
                'name' => 'id_media_object',
                'type' => 'integer',
                'required' => false,
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
                'filters' => NULL,
                'index' => [
                    'id_media_object' => 'index'
                ],
            ],
            'dynamic_values' => [
                'title' => 'Dynamic_values',
                'name' => 'dynamic_values',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'sort' => [
                'title' => 'Sort',
                'name' => 'sort',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ],
                ],
                'filters' => NULL,
            ],
            'children' => [
                'title' => 'children',
                'name' => 'children',
                'type' => 'relation',
                'required' => false,
                'filters' => null,
                'validators' => null,
                'relation' => [
                    'type' => 'hasMany',
                    'class' => '\Pressmind\ORM\Object\CategoryTree\Item',
                    'related_id' => 'id_parent',
                    'order_columns' => [
                        'sort' => 'ASC'
                    ]
                ],
            ]
        ],
    ];
}
