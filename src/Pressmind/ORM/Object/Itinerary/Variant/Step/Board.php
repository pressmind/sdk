<?php


namespace Pressmind\ORM\Object\Itinerary\Variant\Step;


use Pressmind\ORM\Object\AbstractObject;

/**
 * Class Board
 * @package Pressmind\ORM\Object\Itinerary\Variant\Step
 * @property integer $id
 * @property integer $id_step
 * @property boolean $breakfast
 * @property boolean $lunch
 * @property boolean $dinner
 */
class Board extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => 'Board',
            'namespace' => 'Pressmind\ORM\Object\Itinerary\Variant\Step'
        ],
        'database' => [
            'table_name' => 'pmt2core_itinerary_step_boards',
            'primary_key' => 'id'
        ],
        'properties' => [
            'id' => [
                'title' => 'id',
                'name' => 'id',
                'type' => 'integer',
                'required' => true,
                'validators' => null,
                'filters' => null
            ],
            'id_step' => [
                'title' => 'id_step',
                'name' => 'id_step',
                'type' => 'integer',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'breakfast' => [
                'title' => 'breakfast',
                'name' => 'breakfast',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'lunch' => [
                'title' => 'breakfast',
                'name' => 'breakfast',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => null
            ]
            ,
            'dinner' => [
                'title' => 'breakfast',
                'name' => 'breakfast',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => null
            ]
        ]
    ];
}
