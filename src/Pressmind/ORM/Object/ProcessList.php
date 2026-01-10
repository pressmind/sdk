<?php

namespace Pressmind\ORM\Object;

use DateTime;
use Exception;
use Pressmind\Registry;

/**
 * Class ProcessList
 * @package Pressmind\ORM\Object
 * @property integer $id
 * @property string $name
 * @property integer $pid
 * @property integer $timeout
 * @property DateTime $created_at
 */
class ProcessList extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = false;
    protected $_disable_cache_permanently = true;

    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_process_list',
            'primary_key' => 'id',
            'order_columns' => [
                'id' => 'ASC'
            ],
            'indexes' => [
                'name_unique' => [
                    'type' => 'unique',
                    'columns' => [
                        'name'
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
            'name' => [
                'title' => 'name',
                'name' => 'name',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 100,
                    ]
                ],
            ],
            'pid' => [
                'title' => 'pid',
                'name' => 'pid',
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
            'timeout' => [
                'title' => 'timeout',
                'name' => 'timeout',
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
            'created_at' => [
                'title' => 'created_at',
                'name' => 'created_at',
                'type' => 'datetime',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
        ]
    ];

    /**
     * Setzt einen Lock für den angegebenen Prozess
     * @param string $name Prozessname (z.B. "import")
     * @param int $pid Prozess-ID
     * @param int $timeout Timeout in Sekunden (default: 86400)
     * @return bool
     * @throws Exception
     */
    public static function lock($name, $pid, $timeout = 86400)
    {
        self::unlock($name);
        $lock = new self();
        $lock->name = $name;
        $lock->pid = $pid;
        $lock->timeout = $timeout;
        $lock->created_at = new DateTime();
        $lock->create();

        return true;
    }

    /**
     * @param string $name Prozessname
     * @return bool
     * @throws Exception
     */
    public static function unlock($name)
    {
        /** @var \Pressmind\DB\Adapter\Pdo $db */
        $db = Registry::getInstance()->get('db');
        $db->delete('pmt2core_process_list', ['name = ?', $name]);

        return true;
    }

    /**
     * @param string $name
     * @return bool
     * @throws Exception
     */
    public static function isLocked($name)
    {
        $lock = self::getLock($name);
        if ($lock === null) {
            return false;
        }
        $createdAt = $lock->created_at;
        if ($createdAt instanceof DateTime) {
            $now = new DateTime();
            $diff = $now->getTimestamp() - $createdAt->getTimestamp();
            if ($diff >= $lock->timeout) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $name
     * @return ProcessList|null
     * @throws Exception
     */
    public static function getLock($name)
    {
        $obj = new self();
        $results = $obj->loadAll('name = "' . $name . '"');
        if (empty($results)) {
            return null;
        }
        return $results[0];
    }
}
