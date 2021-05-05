<?php

namespace Pressmind\ORM\Object\Touristic;

use Pressmind\ORM\Object\AbstractObject;
use \Pressmind\ORM\Object\Touristic\Startingpoint\Option;

/**
 * Class Startingpoint
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string $text
 * @property integer $logic
 * @property Option[] $options
 */
class Startingpoint extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_check_variables_for_existence = false;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_startingpoints',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'Id',
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
            'code' => [
                'title' => 'Code',
                'name' => 'code',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 45,
                    ],
                ],
                'filters' => NULL,
            ],
            'name' => [
                'title' => 'Name',
                'name' => 'name',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
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
            'logic' => [
                'title' => 'Logic',
                'name' => 'logic',
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
            'options' => [
                'title' => 'Options',
                'name' => 'options',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_startingpoint',
                    'class' => Option::class,
                ],
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
        ]
    );
}
