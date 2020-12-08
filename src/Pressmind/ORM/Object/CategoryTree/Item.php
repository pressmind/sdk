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

    protected $_definitions = array(
        'class' =>
            array(
                'name' => 'CategoryTreeItem',
            ),
        'database' =>
            array(
                'table_name' => 'pmt2core_category_tree_items',
                'primary_key' => 'id',
            ),
        'properties' =>
            array(
                'id' =>
                    array(
                        'title' => 'Id',
                        'name' => 'id',
                        'type' => 'string',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 255,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'id_parent' =>
                    array(
                        'title' => 'Id_parent',
                        'name' => 'id_parent',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 255,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'id_tree' =>
                    array(
                        'title' => 'Id_tree',
                        'name' => 'id_tree',
                        'type' => 'string',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 45,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'name' =>
                    array(
                        'title' => 'Name',
                        'name' => 'name',
                        'type' => 'string',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 255,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'code' =>
                    array(
                        'title' => 'Code',
                        'name' => 'code',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 255,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'id_media_object' =>
                    array(
                        'title' => 'Id_media_object',
                        'name' => 'id_media_object',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 255,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'dynamic_values' =>
                    array(
                        'title' => 'Dynamic_values',
                        'name' => 'dynamic_values',
                        'type' => 'string',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'sort' =>
                    array(
                        'title' => 'Sort',
                        'name' => 'sort',
                        'type' => 'integer',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 11,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
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
            ),
    );
}
