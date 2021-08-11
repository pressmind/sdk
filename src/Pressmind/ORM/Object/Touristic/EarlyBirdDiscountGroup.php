<?php

namespace Pressmind\ORM\Object\Touristic;

use DateTime;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Touristic\EarlyBirdDiscountGroup\Item;

/**
 * Class Earlybird
 * @property integer $id
 * @property string $name
 * @property Item[] $items
 */
class EarlyBirdDiscountGroup extends AbstractObject
{

    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_replace_into_on_create = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_early_bird_discount_group',
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
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => NULL,
            ],
            'items' => [
                'title' => 'Items',
                'name' => 'items',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_early_bird_discount_group',
                    'class' => Item::class,
                    'filters' => null
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
        ]
    );
}
