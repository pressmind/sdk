<?php

namespace Pressmind\ORM\Object\MediaObject\DataType;

use Pressmind\ORM\Object\AbstractObject;

/**
 * @property integer $id
 * @property integer $id_media_object
 * @property string $section_name
 * @property string $language
 * @property string $var_name
 * @property string $id_icon
 * @property string $name
 * @property string $slug
 * @property string $url
 * @property string $mime
 * @property string $style
 * @property array $variants
 */
class Icon extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_media_object_icons',
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
                    ['name' => 'maxlength', 'params' => 22],
                    ['name' => 'unsigned', 'params' => null],
                ],
            ],
            'id_media_object' => [
                'title' => 'id_media_object',
                'name' => 'id_media_object',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => [
                    ['name' => 'maxlength', 'params' => 22],
                    ['name' => 'unsigned', 'params' => null],
                ],
                'index' => ['id_media_object' => 'index'],
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
                'validators' => [['name' => 'maxlength', 'params' => 32]],
                'index' => ['language' => 'index'],
            ],
            'var_name' => [
                'title' => 'var_name',
                'name' => 'var_name',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => [['name' => 'maxlength', 'params' => 255]],
                'index' => ['var_name' => 'index'],
            ],
            'id_icon' => [
                'title' => 'id_icon',
                'name' => 'id_icon',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [['name' => 'maxlength', 'params' => 255]],
            ],
            'name' => [
                'title' => 'name',
                'name' => 'name',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [['name' => 'maxlength', 'params' => 255]],
            ],
            'slug' => [
                'title' => 'slug',
                'name' => 'slug',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [['name' => 'maxlength', 'params' => 255]],
            ],
            'url' => [
                'title' => 'url',
                'name' => 'url',
                'type' => 'longtext',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'mime' => [
                'title' => 'mime',
                'name' => 'mime',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [['name' => 'maxlength', 'params' => 255]],
            ],
            'style' => [
                'title' => 'style',
                'name' => 'style',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [['name' => 'maxlength', 'params' => 255]],
            ],
            'variants' => [
                'title' => 'variants',
                'name' => 'variants',
                'type' => 'json',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
        ],
    ];
}
