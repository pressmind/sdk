<?php

namespace Pressmind\ORM\Object\Touristic\Startingpoint\Option;

use Pressmind\ORM\Object\AbstractObject;

/**
 * Class ZipRange
 * @property string $id_zip_ranges
 * @property string $id_option
 * @property string $from
 * @property string $to
 */
class ZipRange extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_startingpoint_options_zip_ranges',
            'primary_key' => 'id_zip_ranges',
        ],
        'properties' => [
            'id_zip_ranges' => [
                'title' => 'Id_zip_ranges',
                'name' => 'id_zip_ranges',
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
            'id_option' => [
                'title' => 'Id_option',
                'name' => 'id_option',
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
                    'id_option' => 'index'
                ]
            ],
            'from' => [
                'title' => 'From',
                'name' => 'from',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 42,
                    ],
                ],
                'filters' => NULL,
            ],
            'to' => [
                'title' => 'To',
                'name' => 'to',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 42,
                    ],
                ],
                'filters' => NULL,
            ],
        ]
    );
}
