<?php

namespace Custom\MediaType;

use Pressmind\ORM\Object\MediaType\AbstractMediaType;

/**
 * Minimal media type fixture used by integration tests outside the full import scaffolding flow.
 *
 * @property int $id
 * @property int $id_media_object
 * @property string $language
 * @property string $name
 * @property string $code
 */
class Pauschalreise extends AbstractMediaType
{
    protected $_definitions = [
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'objectdata_1',
            'primary_key' => 'id',
            'relation_key' => 'id_media_object',
        ],
        'properties' => [
            'id' => [
                'title' => 'id',
                'name' => 'id',
                'type' => 'integer',
                'required' => true,
                'validators' => null,
                'filters' => null,
            ],
            'id_media_object' => [
                'title' => 'id_media_object',
                'name' => 'id_media_object',
                'type' => 'integer',
                'required' => false,
                'validators' => null,
                'filters' => null,
                'index' => [
                    'id_media_object' => 'index',
                ],
            ],
            'language' => [
                'title' => 'language',
                'name' => 'language',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => null,
                'index' => [
                    'language' => 'index',
                ],
            ],
            'name' => [
                'title' => 'name',
                'name' => 'name',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null,
            ],
            'code' => [
                'title' => 'code',
                'name' => 'code',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null,
            ],
        ],
    ];
}
