<?php

namespace Pressmind\ORM\Object\MediaObject\DataType;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\CategoryTree\Item;

/**
 * Class Plaintext
 * @package Pressmind\ORM\Object\MediaObject\DataType
 * @property integer $id
 * @property integer $id_media_object
 * @property string $section_name
 * @property string $language
 * @property string $var_name
 * @property integer $id_tree
 * @property integer $id_item
 * @property \Pressmind\ORM\Object\CategoryTree $tree
 * @property Item $item;
 */
class Categorytree extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_media_object_tree_items',
            'primary_key' => 'id'
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
            'id_media_object' => [
                'title' => 'id_media_object',
                'name' => 'id_media_object',
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
                    'id_media_object' => 'index'
                ]
            ],
            'section_name' => [
                'title' => 'section_name',
                'name' => 'section_name',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'language' => [
                'title' => 'section_name',
                'name' => 'section_name',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ]
                ],
                'index' => [
                    'language' => 'index'
                ]
            ],
            'var_name'  => [
                'title' => 'var_name',
                'name' => 'var_name',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ]
                ],
                'index' => [
                    'var_name' => 'index'
                ]
            ],
            'id_tree' => [
                'title' => 'id_tree',
                'name' => 'id_tree',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => null,
                'index' => [
                    'id_tree' => 'index'
                ]
            ],
            'id_item' => [
                'title' => 'id_item',
                'name' => 'id_item',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ]
                ],
                'index' => [
                    'id_item' => 'index'
                ]
            ],
            /*'tree' => [
                'title' => 'id_item',
                'name' => 'id_item',
                'type' => 'relation',
                'relation' => array (
                    'type' => 'hasOne',
                    'class' => '\\Pressmind\\ORM\\Object\\CategoryTree',
                    'related_id' => 'id_tree',
                ),
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],*/
            'item' => [
                'title' => 'id_item',
                'name' => 'id_item',
                'type' => 'relation',
                'relation' => array (
                    'type' => 'hasOne',
                    'class' => '\\Pressmind\\ORM\\Object\\CategoryTree\\Item',
                    'related_id' => 'id_item',
                ),
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
        ]

    ];
}
