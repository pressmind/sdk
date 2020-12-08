<?php


namespace Pressmind\ORM\Object\MediaObject\DataType;

use Pressmind\ORM\Object\AbstractObject;

/**
 * Class Location
 * @package Pressmind\ORM\Object\MediaObject\DataType
 * @property integer $id
 * @property integer $id_media_object
 * @property string $section_name
 * @property float $lat
 * @property float $lng
 */
class Location extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => 'Location',
            'namespace' => '\Pressmind\ORM\MediaObject\DataType',
        ],
        'database' => [
            'table_name' => 'pmt2core_media_object_geodata',
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
            'id_media_object' => [
                'title' => 'id_media_object',
                'name' => 'id_media_object',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => null,
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
                'title' => 'language',
                'name' => 'language',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
            'var_name'  => [
                'title' => 'var_name',
                'name' => 'var_name',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
            'title' => [
                'title' => 'title',
                'name' => 'title',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'address' => [
                'title' => 'address',
                'name' => 'address',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'lat' => [
                'title' => 'lat',
                'name' => 'lat',
                'type' => 'float',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'lng' => [
                'title' => 'lng',
                'name' => 'lng',
                'type' => 'float',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ]
        ]
    ];
}
