<?php


namespace Pressmind\ORM\Object\Itinerary\Variant\Step\DocumentMediaObject;


use Pressmind\ORM\Object\AbstractObject;

/**
 * Class Derivative
 * @package Pressmind\ORM\Object\Itinerary\Variant\Step\DocumentMediaObject
 * @property integer $id
 * @property integer $id_document_media_object
 * @property string $name
 * @property string $file_name
 * @property integer $width
 * @property integer $height
 * @property string $path
 * @property string $uri
 */
class Derivative extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_itinerary_step_document_media_object_derivatives',
            'primary_key' => 'id'
        ],
        'properties' => [
            'id' => [
                'title' => 'id',
                'name' => 'id',
                'type' => 'integer',
                'required' => true,
                'validators' => null,
                'filters' => null
            ],
            'id_document_media_object' => [
                'title' => 'id_document_media_object',
                'name' => 'id_document_media_object',
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
                'index' => [
                    'id_document_media_object' => 'index'
                ],
                'filters' => null
            ],
            'name' => [
                'title' => 'name',
                'name' => 'name',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'file_name' => [
                'title' => 'file_name',
                'name' => 'file_name',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'width' => [
                'title' => 'width',
                'name' => 'width',
                'type' => 'integer',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'height' => [
                'title' => 'height',
                'name' => 'height',
                'type' => 'integer',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'path' => [
                'title' => 'path',
                'name' => 'path',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'uri' => [
                'title' => 'uri',
                'name' => 'uri',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ]
        ]
    ];
}
