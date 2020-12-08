<?php


namespace Pressmind\ORM\Object;


/**
 * Class Route
 * @package Pressmind\ORM\Object
 * @property integer $id
 * @property string $route
 * @property integer $id_media_object
 * @property integer $id_object_type
 * @property string $language
 */
class Route extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => 'Route',
            'namespace' => '\Pressmind\ORM\Object',
        ],
        'database' => [
            'table_name' => 'pmt2core_routes',
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
            'route' => [
                'title' => 'route',
                'name' => 'route',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'id_media_object' => [
                'title' => 'id_media_object',
                'name' => 'id_media_object',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'id_object_type' => [
                'title' => 'id_object_type',
                'name' => 'id_object_type',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'language' => [
                'title' => 'language',
                'name' => 'language',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ]
        ]
    ];
}
