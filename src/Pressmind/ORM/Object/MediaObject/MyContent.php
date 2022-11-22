<?php


namespace Pressmind\ORM\Object\MediaObject;


use DateTime;
use Pressmind\ORM\Object\AbstractObject;

/**
 * Class MyContent
 * @package Pressmind\ORM\Object\MediaObject
 * @property integer $id
 * @property integer $id_media_object
 * @property integer $id_my_content
 * @property string $import_id
 * @property string $editor_link
 * @property string $detail_link
 * @property string $detail_name
 * @property string $detail_code
 * @property string $detail_checksum
 * @property boolean $is_intern
 * @property DateTime $last_update
 */
class MyContent extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_replace_into_on_create = true;

    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_media_object_my_contents',
            'primary_key' => 'id'
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
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => null
            ],
            'id_media_object' => [
                'title' => 'ID MediaObject',
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
                'filters' => null,
                'index' => [
                    'id_media_object' => 'index'
                ]
            ],
            'id_my_content' => [
                'title' => 'ID MyContent',
                'name' => 'id_my_content',
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
                'filters' => null,
                'index' => [
                    'id_my_content' => 'index'
                ]
            ],
            'import_id' => [
                'title' => 'Import ID',
                'name' => 'import_id',
                'type' => 'string',
                'required' => false,
                'filters' => null,
            ],
            'editor_link' => [
                'title' => 'Editor link',
                'name' => 'editor_link',
                'type' => 'string',
                'required' => false,
                'filters' => null
            ],
            'detail_link' => [
                'title' => 'Detail link',
                'name' => 'detail_link',
                'type' => 'string',
                'required' => false,
                'filters' => null
            ],
            'detail_name' => [
                'title' => 'Detail name',
                'name' => 'detail_name',
                'type' => 'string',
                'required' => false,
                'filters' => null
            ],
            'detail_code' => [
                'title' => 'Detail code',
                'name' => 'detail_code',
                'type' => 'string',
                'required' => false,
                'filters' => null
            ],
            'detail_checksum' => [
                'title' => 'Detail checksum',
                'name' => 'detail_checksum',
                'type' => 'string',
                'required' => false,
                'filters' => null
            ],
            'is_intern' => [
                'title' => 'Is intern',
                'name' => 'is_intern',
                'type' => 'boolean',
                'required' => false,
                'filters' => null
            ],
            'last_update' => [
                'title' => 'Last update',
                'name' => 'last_update',
                'type' => 'datetime',
                'required' => false,
                'validators' => null,
                'filters' => null
            ]
        ]
    ];
}
