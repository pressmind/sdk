<?php


namespace Pressmind\ORM\Object;

/**
 * Class Brand
 * @package Pressmind\ORM\Object
 * @property integer $id
 * @property string $name
 * @property string $tags
 * @property string $description
 */
class Brand extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = [
        'class' => [
            'name' => 'Brand',
            'namespace' => '\Pressmind\ORM\Object',
        ],
        'database' => [
            'table_name' => 'pmt2core_brands',
            'primary_key' => 'id',
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
                ],
            ],
            'name' => [
                'title' => 'name',
                'name' => 'name',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
            ],
            'tags' => [
                'title' => 'tags',
                'name' => 'tags',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'description' => [
                'title' => 'description',
                'name' => 'description',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
        ]
    ];
}
