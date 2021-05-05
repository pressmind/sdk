<?php

namespace Pressmind\ORM\Object\Touristic\Option;

use Pressmind\ORM\Object\AbstractObject;

/**
 * Class Description
 * @property string $id_booking_package
 * @property integer $id_media_object
 * @property string $type
 * @property string $name
 * @property string $text
 * @property boolean $necessary
 */
class Description extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_option_descriptions',
            'primary_key' => 'id_booking_package',
        ],
        'properties' => [
            'id_booking_package' => [
                'title' => 'Id_booking_package',
                'name' => 'id_booking_package',
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
            'id_media_object' => [
                'title' => 'Id_media_object',
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
            'type' => [
                'title' => 'Type',
                'name' => 'type',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'inarray',
                        'params' => [
                            'extra',
                            'sightseeing',
                            'ticket',
                        ],
                    ],
                ],
                'filters' => NULL,
            ],
            'name' => [
                'title' => 'Name',
                'name' => 'name',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'text' => [
                'title' => 'Text',
                'name' => 'text',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'necessary' => [
                'title' => 'Necessary',
                'name' => 'necessary',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => NULL,
            ],
        ]
    );
}
