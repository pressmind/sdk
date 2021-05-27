<?php

namespace Pressmind;


use Exception;
use Pressmind\Cache\Adapter\Factory;
use Pressmind\DB\Adapter\Pdo;
use Pressmind\Log\Writer;
use Pressmind\Search\Condition\ConditionInterface;
use Pressmind\Search\Paginator;
use Pressmind\Search\Result;

/**
 * Class Search
 * @package Pressmind - Search
 */
class Search
{
    /**
     * @var ConditionInterface[]
     */
    private $_conditions = [];

    /**
     * @var string
     */
    private $_sql;

    /**
     * @var array
     */
    private $_values = [];

    /**
     * @var array
     */
    private $_sort_properties = [];

    /**
     * @var array
     */
    private $_sort_properties_database_mapper = [
        'price' => 'pmt2core_cheapest_price_speed.price_total'
    ];

    /**
     * @var array
     */
    private $_limits = [];

    /**
     * @var Paginator
     */
    private $_paginator = null;

    /**
     * @var integer
     */
    private $_total_result_count;

    /**
     * @var Result
     */
    private $_result = null;

    /**
     * Search constructor.
     * @param array $pConditions
     * @param array $pLimits
     * @param array $pSortProperties
     */
    public function __construct($pConditions = [], $pLimits = [], $pSortProperties = [])
    {
        //if(!is_array($pConditions)) $pConditions = [$pName => $pConditions];
        foreach ($pConditions as $name => $condition) {
            $this->addCondition($name, $condition);
        }
        $this->setLimits($pLimits);
        $this->setSortProperties($pSortProperties);
    }

    /**
     * @param string $pName
     * @param ConditionInterface $pCondition
     */
    public function addCondition($pName, $pCondition)
    {
        $this->_conditions[$pName] = $pCondition;
    }

    /**
     * @param array $limits
     */
    public function setLimits($limits)
    {
        $this->_limits = $limits;
    }

    /**
     * @param array $sort_properties
     */
    public function setSortProperties($sort_properties)
    {
        $this->_sort_properties = $sort_properties;
    }

    /**
     * @param Paginator $paginatorObject
     */
    public function setPaginator($paginatorObject)
    {
        $this->_paginator = $paginatorObject;
    }

    /**
     * @return Paginator
     */
    public function getPaginator()
    {
        return $this->_paginator;
    }

    /**
     * @return int
     */
    public function getTotalResultCount()
    {
        return $this->_total_result_count;
    }

    /**
     * @return Result
     * @throws Exception
     */
    public function exec($disablePaginator = false)
    {
        /**@var Pdo $db*/
        $db = Registry::getInstance()->get('db');
        $total_count = 0;
        if(!is_null($this->_paginator)) {
            $this->_concatSql(true);
            $total_count_result = $db->fetchRow($this->_sql, $this->_values);
            $total_count = $total_count_result->total_rows;
            if(!empty($this->_limits)) {
                if($this->_limits['length'] <= $total_count) {
                    $total_count = $this->_limits['length'];
                }
            }
            if(!$disablePaginator) {
                $this->_limits = $this->_paginator->getLimits($total_count);
            } else {
                $this->_limits = [];
            }
        }
        $this->_concatSql();
        if(Registry::getInstance()->get('config')['cache']['enabled'] && in_array('SEARCH', Registry::getInstance()->get('config')['cache']['types'])) {
            $key = 'SEARCH_' . md5($this->_sql . json_encode($this->_values));
            $cache_adapter = Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
            if ($cache_adapter->exists($key)) {
                Writer::write(get_class($this) . ' exec() reading from cache. KEY: ' . $key, Writer::OUTPUT_FILE, strtolower(Registry::getInstance()->get('config')['cache']['adapter']['name']), Writer::TYPE_DEBUG);
                $db_result = json_decode($cache_adapter->get($key));
            } else {
                $db_result = $db->fetchAll($this->_sql, $this->_values);
                Writer::write(get_class($this) . ' exec() writing to cache. KEY: ' . $key, Writer::OUTPUT_FILE, strtolower(Registry::getInstance()->get('config')['cache']['adapter']['name']), Writer::TYPE_DEBUG);
                $info = new \stdClass();
                $info->type = 'SEARCH';
                $info->classname = self::class;
                $info->method = 'updateCache';
                $info->parameters = ['sql' => $this->_sql, 'values' => $this->_values];
                $cache_adapter->add($key, json_encode($db_result), $info);
            }
        } else {
            $db_result = $db->fetchAll($this->_sql, $this->_values);
        }
        $result = new Result();
        $result->setQuery($this->_sql);
        $result->setValues($this->_values);
        $result->setResultRaw($db_result);
        if(!is_null($this->_paginator)) {
            $this->_total_result_count = $total_count;
        } else {
            $this->_total_result_count = count($db_result);
        }
        return $result;
    }

    /**
     * @param \stdClass $params
     */
    public function updateCache($params) {
        try {
            $cache_adapter = Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
            $key = 'SEARCH_' . md5($params->sql . json_encode($params->values));
            /**@var Pdo $db */
            $db = Registry::getInstance()->get('db');
            $db_result = $db->fetchAll($params->sql, (array)$params->values);
            $info = new \stdClass();
            $info->type = 'SEARCH';
            $info->classname = self::class;
            $info->method = 'updateCache';
            $info->parameters = ['sql' => $params->sql, 'values' => $params->values];
            Writer::write(get_class($this) . ' exec() writing to cache. KEY: ' . $key, Writer::OUTPUT_FILE, strtolower(Registry::getInstance()->get('config')['cache']['adapter']['name']), Writer::TYPE_DEBUG);
            $cache_adapter->add($key, json_encode($db_result), $info);
            return $key. ': ' . $params->sql;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * @param boolean $loadFull
     * @return ORM\Object\MediaObject[]
     * @throws Exception
     */
    public function getResults($loadFull = false, $disablePaginator = false)
    {
        if(true === $disablePaginator) {
            return $this->exec(true)->getResult($loadFull);
        }
        if(is_null($this->_result)) {
            $this->_result = $this->exec();
        }
        return $this->_result->getResult($loadFull);
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->_sql;
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return $this->_values;
    }

    /**
     * @param bool $returnTotalCount
     */
    public function _concatSql($returnTotalCount = false)
    {
        $sql_sorted = [];
        $joins_sorted = [];
        $additional_fields_sorted = [];
        $joins = [];
        $sql = [];
        $additional_fields = [];
        $additional_fields_string = '';
        $config = Registry::getInstance()->get('config');
        $db_engine = $config['database']['engine'];
        foreach ($this->_conditions as $key => $condition) {
            if(!isset($joins_sorted[$condition->getSort()])) $joins_sorted[$condition->getSort()] = [];
            if(!is_null($condition->getJoins()) && !in_array($condition->getJoins(), $joins_sorted[$condition->getSort()])) {
                $joins_sorted[$condition->getSort()][] = $condition->getJoins();
            }
            if(!is_null($condition->getAdditionalFields())) {
                $additional_fields_sorted[$condition->getSort()][] = $condition->getAdditionalFields();
            }
            $sql_sorted[$condition->getSort()][] = $condition->getSQL();
            $this->_values = array_merge($condition->getValues(), $this->_values);
        }
        if(!empty($additional_fields)) {
            $additional_fields_string = ' , ' . implode(', ', $additional_fields);
        }

        ksort($sql_sorted);
        ksort($joins_sorted);
        ksort($additional_fields_sorted);

        /**
         * Flatten the arrays after sorting
         */
        foreach ($sql_sorted as $sort => $sql_entries) {
            foreach ($sql_entries as $sql_entry) {
                $sql[] = $sql_entry;
            }
        }
        foreach ($joins_sorted as $sort => $joins_entries) {
            foreach ($joins_entries as $join_entry) {
                if(!in_array($join_entry, $joins)) {
                    $joins[] = $join_entry;
                }
            }
        }
        foreach ($additional_fields_sorted as $sort => $additional_fields_entries) {
            foreach ($additional_fields_entries as $additional_fields_entry) {
                $additional_fields[] = $additional_fields_entry;
            }
        }

        $sql_start = "SELECT DISTINCT pmt2core_media_objects.id";
        $sql_end = "";
        if($returnTotalCount == true) {
            $sql_start = "SELECT COUNT(DISTINCT pmt2core_media_objects.id) as total_rows";
            $sql_end = "";
        }
        $visibility = " pmt2core_media_objects.visibility = 30 AND ";
        if($this->hasCondition('Pressmind\Search\Condition\Visibility') || $this->hasCondition('Pressmind\Search\Condition\DataView')) {
            $visibility = null;
        }
        $now = new \DateTime();
        $validity = " ((pmt2core_media_objects.valid_from IS NULL OR pmt2core_media_objects.valid_from <= '" . $now->format('Y-m-d H:i:00') . "') AND (pmt2core_media_objects.valid_to IS NULL OR pmt2core_media_objects.valid_to >= '" . $now->format('Y-m-d H:i:00') . "')) AND ";
        if($this->hasCondition('Pressmind\Search\Condition\Validity')) {
            $validity = null;
        }

        $this->_sql = $sql_start . $additional_fields_string . " FROM pmt2core_media_objects " . implode(' ', $joins) . " WHERE" . $visibility . $validity . " (" . implode(') AND (', $sql). ")" . $sql_end;
        if(empty($this->_conditions)) {
            $this->_sql = $sql_start . " FROM pmt2core_media_objects " . " WHERE pmt2core_media_objects.visibility = 30" . $sql_end;
        }
        if(!empty($this->_sort_properties)  && $returnTotalCount == false) {
            $order_strings = [];
            foreach ($this->_sort_properties as $property => $direction) {
                if(array_key_exists($property, $this->_sort_properties_database_mapper)) {
                    $property = $this->_sort_properties_database_mapper[$property];
                }
                if($direction == 'RAND()') {
                    $order_strings[] = 'RAND()';
                } else {
                    if(strtolower($db_engine) == 'mariadb') {
                        $order_strings[] = $property . ' ' . $direction;
                    } else {
                        $order_strings[] = 'ANY_VALUE(' . $property . ') ' . $direction;
                    }
                }
            }
            $this->_sql .= ' ORDER BY ' . implode(', ', $order_strings);
        }
        if(!empty($this->_limits) && $returnTotalCount == false) {
            $this->_sql .= " LIMIT " . $this->_limits['start'] . ', ' . $this->_limits['length'];
        }
    }

    /**
     * @param $pClassName
     * @return bool
     */
    public function hasCondition($pClassName)
    {
        foreach ($this->_conditions as $condition) {
            if(is_a($condition, $pClassName)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $pClassName
     * @return ConditionInterface|bool
     */
    public function getCondition($pClassName)
    {
        foreach ($this->_conditions as $condition) {
            if(is_a($condition, $pClassName)) {
                return $condition;
            }
        }
        return false;
    }

    /**
     * @return false|string
     */
    public function getConditionsAsJSON() {
        $data = [
            'limit' => $this->_limits,
            'sort' => [
                'property' => key($this->_sort_properties),
                'direction' => $this->_sort_properties[key($this->_sort_properties)]
            ],
            'pagination' => [
                'pagesize' => 100
            ]
        ];
        foreach ($this->_conditions as $name => $condition) {
            $data['conditions'][$name] = $condition->toJSON();
        }
        return json_encode($data);
    }
}
