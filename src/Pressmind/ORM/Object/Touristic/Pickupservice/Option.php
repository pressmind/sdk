<?php

namespace Pressmind\ORM\Object\Touristic\Pickupservice;

use Pressmind\ORM\Object\AbstractObject;

/**
 * Class Option
 * @property string $id
 * @property string $id_pickupservice
 * @property integer $zip
 * @property string $code
 * @property string $name
 * @property float $price
 * @property float $distance
 * @property string $text
 */
class Option extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_pickupservice_options',
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
            'id_pickupservice' => [
                'title' => 'Id_pickupservice',
                'name' => 'id_pickupservice',
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
                    'id_pickupservice' => 'index'
                ]
            ],
            'zip' => [
                'title' => 'Zip',
                'name' => 'zip',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 5,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
                'index' => [
                    'zip' => 'index'
                ]
            ],
            'code' => [
                'title' => 'Code',
                'name' => 'code',
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
            'name' => [
                'title' => 'Name',
                'name' => 'name',
                'type' => 'string',
                'required' => true,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'price' => [
                'title' => 'Price',
                'name' => 'price',
                'type' => 'float',
                'required' => true,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'distance' => [
                'title' => 'Distance',
                'name' => 'distance',
                'type' => 'float',
                'required' => true,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'text' => [
                'title' => 'Text',
                'name' => 'text',
                'type' => 'string',
                'required' => true,
                'validators' => NULL,
                'filters' => NULL,
            ],
        ]
    );
}
