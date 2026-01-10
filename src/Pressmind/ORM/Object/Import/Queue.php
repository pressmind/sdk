<?php

namespace Pressmind\ORM\Object\Import;

use DateTime;
use Exception;
use Pressmind\ORM\Object\AbstractObject;

/**
 * Class Queue
 * @package Pressmind\ORM\Object\Import
 * @property integer $id
 * @property integer $id_media_object
 * @property string $source
 * @property string $action
 * @property DateTime $created_at
 */
class Queue extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = false;
    protected $_disable_cache_permanently = true;

    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_import_queue',
            'primary_key' => 'id',
            'order_columns' => [
                'id' => 'ASC'
            ],
            'indexes' => [
                'id_media_object_unique' => [
                    'type' => 'unique',
                    'columns' => [
                        'id_media_object'
                    ]
                ]
            ]
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
            'id_media_object' => [
                'title' => 'id_media_object',
                'name' => 'id_media_object',
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
            'source' => [
                'title' => 'source',
                'name' => 'source',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255
                    ]
                ]
            ],
            'action' => [
                'title' => 'action',
                'name' => 'action',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 50
                    ]
                ]
            ],
            'created_at' => [
                'title' => 'created_at',
                'name' => 'created_at',
                'type' => 'datetime',
                'required' => true,
                'filters' => null,
                'validators' => null
            ]
        ]
    ];

    /**
     * @param int $id_media_object
     * @param string $source The source/origin of the import (e.g. 'fullimport', 'api_import', 'webhook')
     * @param string $action The action to perform (e.g. 'mediaobject', 'touristic')
     * @return bool
     * @throws Exception
     */
    public static function addToQueue($id_media_object, $source = 'fullimport', $action = 'mediaobject')
    {
        if (self::exists($id_media_object)) {
            return false;
        }

        $queue = new self();
        $queue->id_media_object = (int)$id_media_object;
        $queue->source = $source;
        $queue->action = $action;
        $queue->created_at = new DateTime();
        $queue->create();

        return true;
    }

    /**
     * @return array
     * @throws Exception
     */
    public static function getAllPending()
    {
        $queue = new self();
        $entries = $queue->loadAll(null, ['id' => 'ASC']);

        $ids = [];
        foreach ($entries as $entry) {
            $ids[] = $entry->id_media_object;
        }

        return $ids;
    }

    /**
     * @return array
     * @throws Exception
     */
    public static function getAllPendingWithAction()
    {
        $queue = new self();
        $entries = $queue->loadAll(null, ['id' => 'ASC']);

        $result = [];
        foreach ($entries as $entry) {
            $result[] = [
                'id_media_object' => $entry->id_media_object,
                'action' => $entry->action ?? 'mediaobject'
            ];
        }

        return $result;
    }

    /**
     * @param int $id_media_object
     * @return bool
     * @throws Exception
     */
    public static function exists($id_media_object)
    {
        $queue = new self();
        $entries = $queue->loadAll(['id_media_object' => (int)$id_media_object]);
        return count($entries) > 0;
    }

    /**
     * @param int $id_media_object
     * @return bool
     * @throws Exception
     */
    public static function remove($id_media_object)
    {
        $queue = new self();
        $entries = $queue->loadAll(['id_media_object' => (int)$id_media_object]);

        if (count($entries) > 0) {
            foreach ($entries as $entry) {
                $entry->delete();
            }
            return true;
        }

        return false;
    }

    /**
     * @return int
     * @throws Exception
     */
    public static function count()
    {
        $queue = new self();
        return $queue->getTableRowCount();
    }

    /**
     * @return void
     * @throws Exception
     */
    public static function clear()
    {
        $queue = new self();
        $queue->truncate();
    }
}
