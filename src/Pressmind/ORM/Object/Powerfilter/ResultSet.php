<?php
namespace Pressmind\ORM\Object\Powerfilter;
use Pressmind\ORM\Object\AbstractObject;

/**
 * Class ResultSet
 * @package Pressmind\ORM\Object
 * @property integer $id
 * @property string $id_media_objects
 */
class ResultSet extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;
    protected $_replace_into_on_create = true;

    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_powerfilter_result_set',
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
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
            ],
            'id_media_objects' => [
                'title' => 'id_media_objects',
                'name' => 'id_media_objects',
                'type' => 'longtext',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ]
        ]
    ];
}