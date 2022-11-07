<?php

namespace Pressmind\ORM\Object\Touristic\Insurance;

use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Touristic\Insurance;
use Pressmind\Registry;

/**
 * Class Attribute
 * @property string $id
 * @property string $name
 * @property string $description
 * @property string $code
 * @property string $code_ibe
 * @property integer $order
 */
class Attribute extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_replace_into_on_create = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_insurance_attributes',
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
            'name' => [
                'title' => 'Name',
                'name' => 'name',
                'type' => 'string',
                'required' => false,
                'filters' => NULL,
            ],
            'description' => [
                'title' => 'description',
                'name' => 'description',
                'type' => 'string',
                'required' => false,
                'filters' => NULL,
            ],
            'order' => [
                'title' => 'order',
                'name' => 'order',
                'type' => 'integer',
                'required' => false,
                'filters' => NULL,
            ],
            'code' => [
                'title' => 'code',
                'name' => 'code',
                'type' => 'string',
                'required' => false,
                'filters' => NULL,
            ],
            'code_ibe' => [
                'title' => 'code_ibe',
                'name' => 'code_ibe',
                'type' => 'string',
                'required' => false,
                'filters' => NULL,
            ],
        ]
    );

}
