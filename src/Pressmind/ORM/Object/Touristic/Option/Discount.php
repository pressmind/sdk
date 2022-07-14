<?php


namespace Pressmind\ORM\Object\Touristic\Option;

use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Touristic\Option\Discount\Scale;

/**
 * Class Discount
 * @package Pressmind\ORM\Object\Touristic\Option
 * @property string $id
 * @property string $name
 * @property boolean $active
 * @property Scale[] $scales
 */
class Discount extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_replace_into_on_create = true;

    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_option_discounts',
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
            'active' => [
                'title' => 'active',
                'name' => 'active',
                'type' => 'boolean',
                'required' => true,
                'validators' => null,
                'filters' => NULL,
            ],
            'scales' => [
                'title' => 'Scales',
                'name' => 'scales',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_touristic_option_discount',
                    'class' => Scale::class,
                    'filters' => null
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
        ]
    ];
}
