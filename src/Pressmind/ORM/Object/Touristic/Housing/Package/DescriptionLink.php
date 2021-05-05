<?php


namespace Pressmind\ORM\Object\Touristic\Housing\Package;


use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\MediaObject;

/**
 * Class DescriptionLink
 * @package Pressmind\ORM\Object\Touristic\Housing\Package
 * @property string $id
 * @property string $id_housing_package
 * @property integer $id_media_object
 * @property integer $sort
 * @property MediaObject $media_object
 */
class DescriptionLink extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_housing_package_description_links',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'ID',
                'name' => 'id',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ],
                ],
                'filters' => NULL,
            ],
            'id_housing_package' => [
                'title' => 'ID Housing Package',
                'name' => 'id_housing_package',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ],
                ],
                'filters' => NULL,
                'index' => [
                    'id_housing_package' => 'index'
                ]
            ],
            'id_media_object' => [
                'title' => 'ID Media Object',
                'name' => 'id_media_object',
                'type' => 'integer',
                'required' => true,
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
                'filters' => NULL,
                'index' => [
                    'id_media_object' => 'index'
                ]
            ],
            'sort' => [
                'title' => 'Sort',
                'name' => 'sort',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
            ],
            'media_object' => [
                'title' => 'Media Object',
                'name' => 'media_object',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasOne',
                    'related_id' => 'id_media_object',
                    'class' => MediaObject::class,
                    'filters' => null
                ],
                'required' => false,
            ]
        ]
    );
}
