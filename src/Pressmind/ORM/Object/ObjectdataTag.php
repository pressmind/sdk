<?php

namespace Pressmind\ORM\Object;

/**
 * Class ObjectdataTag
 * @property integer $id
 * @property integer $id_object_type
 * @property string $objectdata_column_name
 * @property string $tag_name
 */
class ObjectdataTag extends AbstractObject
{
    protected $_definitions = [
        'class' => [
           'name' => 'ObjectdataTag',
        ],
        'database' => [
            'table_name' => 'pmt2core_objectdata_tags',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'ID',
                'name' => 'id',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                   [
                       'name' => 'maxlength',
                       'params' => 22,
                   ],
                ],
                'filters' => null,
            ],
            'id_object_type' => [
                'title' => 'Media Object Type ID',
                'name' => 'id_object_type',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                ],
                'filters' => null,
            ],
            'objectdata_column_name' => [
                'title' => 'Column Name',
                'name' => 'objectdata_column_name',
                'type' => 'string',
                'required' => true,
                'validators' => null,
                'filters' => null
            ],
            'tag_name' => [
                'title' => 'Tag Name',
                'name' => 'tag_name',
                'type' => 'string',
                'required' => true,
                'validators' => null,
                'filters' => null
            ]
        ]
    ];
}
