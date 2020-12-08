<?php


namespace Pressmind\ORM\Object\MediaObject\DataType\Picture;


use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\MediaObject\DataType\Picture;

/**
 * Class Derivative
 * @package Pressmind\ORM\Object\MediaObject\DataType\Picture
 * @property integer $id
 * @property integer $id_media_object
 * @property integer $id_image
 * @property integer $id_image_section
 * @property string $name
 * @property string $file_name
 * @property integer $width
 * @property integer $height
 * @property boolean $download_successful
 */
class Derivative extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => 'Derivative',
            'namespace' => '\Pressmind\ORM\MediaObject\DataType\Image',
        ],
        'database' => [
            'table_name' => 'pmt2core_media_object_image_derivatives',
            'primary_key' => 'id'
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
            'id_image' => [
                'title' => 'id_image',
                'name' => 'id_image',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'id_image_section' => [
                'title' => 'id_image_section',
                'name' => 'id_image_section',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'name' => [
                'title' => 'name',
                'name' => 'name',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'file_name' => [
                'title' => 'file_name',
                'name' => 'file_name',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'download_successful'  => [
                'title' => 'download_successful',
                'name' => 'download_successful',
                'type' => 'boolean',
                'required' => false,
                'filters' => null,
                'validators' => null,
                'default_value' => false
            ],
            'width' => [
                'title' => 'width',
                'name' => 'width',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
            'height' => [
                'title' => 'height',
                'name' => 'height',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
        ]
    ];
}
