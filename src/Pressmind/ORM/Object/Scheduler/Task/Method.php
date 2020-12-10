<?php


namespace Pressmind\ORM\Object\Scheduler\Task;


use Pressmind\ORM\Object\AbstractObject;

/**
 * Class Method
 * @package Pressmind\ORM\Object\Scheduler\Task
 * @property integer $id
 * @property integer $task_id
 * @property string $name
 * @property string $parameters
 * @property integer $position
 */
class Method extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_scheduler_task_method',
            'primary_key' => 'id',
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
            'task_id' => [
                'title' => 'task_id',
                'name' => 'task_id',
                'type' => 'integer',
                'required' => true,
                'validators' => null,
                'filters' => null
            ],
            'name' => [
                'title' => 'name',
                'name' => 'name',
                'type' => 'string',
                'required' => true,
                'validators' => null,
                'filters' => null
            ],
            'parameters' => [
                'title' => 'parameters',
                'name' => 'parameters',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'position' => [
                'title' => 'position',
                'name' => 'position',
                'type' => 'integer',
                'required' => true,
                'validators' => null,
                'filters' => null
            ]
        ]
    ];
}
