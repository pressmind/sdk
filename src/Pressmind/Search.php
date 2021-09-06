<?php

namespace Pressmind;


use Exception;
use Pressmind\Cache\Adapter\Factory;
use Pressmind\DB\Adapter\Pdo;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\Search\Condition\ConditionInterface;
use Pressmind\Search\Filter\PriceRange;
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
    private $_subselect_joins = [];

    /**
     * @var array
     */
    private $_sort_properties_database_mapper = [
        'price' => 'cheapest_price_total',
        'date_departure' => 'cheapest_price_speed.date_departure'
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
     * @var bool
     */
    public $return_id_only = false;

    /**
     * @var bool
     */
    private $_skip_cache = false;

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
            if(false == $disablePaginator) {
                $this->_concatSql(true);
                if (Registry::getInstance()->get('config')['cache']['enabled'] && in_array('SEARCH', Registry::getInstance()->get('config')['cache']['types']) && $this->_skip_cache == false) {
                    $key = $this->generateCacheKey('_COUNT');
                    $cache_adapter = Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
                    if ($cache_adapter->exists($key)) {
                        $total_count = $cache_adapter->get($key);
                    } else {
                        $total_count_result = $db->fetchRow($this->_sql, $this->_values);
                        $total_count = $total_count_result->total_rows;
                        $info = new \stdClass();
                        $info->type = 'SEARCH_COUNT';
                        $info->classname = self::class;
                        $info->method = 'updateCache';
                        $info->parameters = ['sql' => $this->_sql, 'values' => $this->_values];
                        $cache_adapter->add($key, $total_count, $info);
                    }
                } else {
                    $total_count_result = $db->fetchRow($this->_sql, $this->_values);
                    $total_count = $total_count_result->total_rows;
                }
            }
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
        $class_name = MediaObject::class;
        if (true === $this->return_id_only) {
            $class_name = null;
        }
        $isCached = false;
        if (Registry::getInstance()->get('config')['cache']['enabled'] && in_array('SEARCH', Registry::getInstance()->get('config')['cache']['types']) && $this->_skip_cache == false) {
            $key = $this->generateCacheKey();
            $cache_adapter = Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
            if ($cache_adapter->exists($key)) {
                Writer::write(get_class($this) . ' exec() reading from cache. KEY: ' . $key, Writer::OUTPUT_FILE, strtolower(Registry::getInstance()->get('config')['cache']['adapter']['name']), Writer::TYPE_DEBUG);
                $cache_contents = json_decode($cache_adapter->get($key));
                $db_result = [];
                foreach ($cache_contents as $cache_content) {
                    $mo = new ORM\Object\MediaObject();
                    $mo->fromStdClass($cache_content);
                    $db_result[] = $mo;
                }
            } else {
                $db_result = $db->fetchAll($this->_sql, $this->_values, $class_name);
                Writer::write(get_class($this) . ' exec() writing to cache. KEY: ' . $key, Writer::OUTPUT_FILE, strtolower(Registry::getInstance()->get('config')['cache']['adapter']['name']), Writer::TYPE_DEBUG);
                $info = new \stdClass();
                $info->type = 'SEARCH';
                $info->classname = self::class;
                $info->method = 'updateCache';
                $info->parameters = ['sql' => $this->_sql, 'values' => $this->_values];
                $cache_adapter->add($key, json_encode($db_result), $info);
            }
            $isCached = $key;
        } else {
            $db_result = $db->fetchAll($this->_sql, $this->_values, $class_name);
        }
        $result = new Result($isCached);
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
     * @param string $add
     * @return string
     */
    public function generateCacheKey($add = '')
    {
        return 'SEARCH' . $add . '_' . md5($this->_sql . json_encode($this->_values));
    }

    public function isCached()
    {
        return $this->_result->isCached();
    }

    public function getCacheInfo($key = null)
    {
        if($this->isCached() !== false) {
            if(is_null($key)) {
                $key = $this->generateCacheKey();
            }
            $cache_adapter = Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
            return $cache_adapter->getInfo($key);
        }
        return null;
    }

    public function disableCache()
    {
        $this->_skip_cache = true;
    }

    /**
     * @param \stdClass|null $params
     */
    public function updateCache($key = null)
    {
        $cache_adapter = Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
        if(is_null($key)) {
            $key = $this->generateCacheKey();
        }
        $info = $this->getCacheInfo($key);
        $params = isset($info['info']) && !empty($info['info']->parameters) ? $info['info']->parameters : null;

        if(!is_null($params)) {
            try {
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
                return $key . ': ' . $params->sql;
            } catch (Exception $e) {
                return $e->getMessage();
            }
        }
        return null;
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

    private function _concatJoins()
    {

    }

    private function concatConditions()
    {

    }

    /**
     * @param ConditionInterface $condition
     * @return string
     */
    private function _addSubselectJoin($condition)
    {
        if(!isset($this->_subselect_joins[$condition->getSubselectJoinTable()])) {
            $this->_subselect_joins[$condition->getSubselectJoinTable()] = ['sort' => $condition->getSort() ,'join' => $condition->getJoins(), 'conditions' => []];
        }
        $this->_subselect_joins[$condition->getSubselectJoinTable()]['conditions'][] = $condition->getSQL();
    }

    private function _concatSubSelectJoin($subselectJoin) {
        $conditions = implode(') AND (', $subselectJoin['conditions']);
        return str_replace('###CONDITIONS###', '(' . $conditions . ')', $subselectJoin['join']);
    }

    /**
     * @param bool $returnTotalCount
     */
    public function _concatSql($returnTotalCount = false)
    {
        $this->_sql = null;
        $this->_values = [];
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
            if (!isset($joins_sorted[$condition->getSort()])) $joins_sorted[$condition->getSort()] = [];
            if(strtolower($condition->getJoinType()) != 'subselect') {
                if (!is_null($condition->getJoins()) && !in_array($condition->getJoins(), $joins_sorted[$condition->getSort()])) {
                    $joins_sorted[$condition->getSort()][] = $condition->getJoins();
                }
                if (!is_null($condition->getAdditionalFields())) {
                    $additional_fields_sorted[$condition->getSort()][] = $condition->getAdditionalFields();
                }
                if (!is_null($condition->getSQL())) {
                    $sql_sorted[$condition->getSort()][] = $condition->getSQL();
                }
            } else {
                $this->_addSubselectJoin($condition);
            }
            $this->_values = array_merge($condition->getValues(), $this->_values);
        }
        if(!empty($this->_subselect_joins)) {
            foreach ($this->_subselect_joins as $key => $subselect_join) {
                $joins_sorted[$subselect_join['sort']][] = $this->_concatSubSelectJoin($subselect_join);
            }
        }
        if(!empty($additional_fields)) {
            $additional_fields_string = ' , ' . implode(', ', $additional_fields);
        }

        ksort($sql_sorted);
        ksort($joins_sorted);
        ksort($additional_fields_sorted);

        $order_strings = [];

        if(!empty($this->_sort_properties)  && $returnTotalCount == false) {
            foreach ($this->_sort_properties as $property => $direction) {
                if(array_key_exists($property, $this->_sort_properties_database_mapper)) {
                    $property = $this->_sort_properties_database_mapper[$property];
                }
                if($direction == 'RAND()') {
                    $order_strings[] = 'RAND()';
                } else {
                    $order_strings[] = $property . ' ' . $direction;
                }
            }
        }

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

        $sql_start = "SELECT pmt2core_media_objects.id";
        if(false === $this->return_id_only) {
            $sql_start = "SELECT pmt2core_media_objects.*";
            if(in_array('cheapest_price_total ASC', $order_strings) || $this->hasCondition('Pressmind\Search\Condition\PriceRange') && $returnTotalCount == false) {
                $sql_start .= ', cheapest_price_total';
            }
        }
        $sql_end = "";
        if(in_array('cheapest_price_total ASC', $order_strings) && !$this->hasCondition('Pressmind\Search\Condition\PriceRange') && $returnTotalCount == false) {
            $joins[] = 'INNER JOIN (SELECT id_media_object, MIN(price_total) as cheapest_price_total
                    FROM pmt2core_cheapest_price_speed
                    WHERE price_total > 0 AND NOT ISNULL(price_total) AND (price_total BETWEEN 1 AND 10000000)
                    AND option_occupancy <= 2
                    GROUP BY id_media_object) cheapest_price_speed On cheapest_price_speed.id_media_object = pmt2core_media_objects.id';
        }
        if($returnTotalCount == true) {
            $sql_start = "SELECT COUNT(DISTINCT pmt2core_media_objects.id) as total_rows";
            $sql_end = "";
        }
        $visibility = " pmt2core_media_objects.visibility = 30 AND ";
        if($this->hasCondition('Pressmind\Search\Condition\Visibility') || $this->hasCondition('Pressmind\Search\Condition\DataView')) {
            $visibility = null;
        }
        $validity = " ((pmt2core_media_objects.valid_from IS NULL OR pmt2core_media_objects.valid_from <= NOW()) AND (pmt2core_media_objects.valid_to IS NULL OR pmt2core_media_objects.valid_to >= NOW())) AND ";
        if($this->hasCondition('Pressmind\Search\Condition\Validity')) {
            $validity = null;
        }
        $this->_sql = $sql_start . $additional_fields_string . " FROM pmt2core_media_objects " . implode(' ', $joins) . " WHERE" . $visibility . $validity . " (" . implode(') AND (', $sql). ")" . $sql_end;
        if(empty($this->_conditions)) {
            $this->_sql = $sql_start . " FROM pmt2core_media_objects " . " WHERE pmt2core_media_objects.visibility = 30" . $sql_end;
        }
        if(!empty($order_strings)) {
            $this->_sql .= ' ORDER BY ' . implode(', ', $order_strings);
        }
        if(!empty($this->_limits) && $returnTotalCount == false) {
            $this->_sql .= " LIMIT " . $this->_limits['start'] . ', ' . $this->_limits['length'];
        }
    }


    public function removeLimits()
    {
        $this->_limits = [];
    }

    public function removeSortProperties() {
        $this->_sort_properties = [];
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
     * @param $pClassName
     * @return ConditionInterface[]
     */
    public function getConditions()
    {
        return $this->_conditions;
    }

    public function removeCondition($pClassName)
    {
        $i = 0;
        foreach ($this->_conditions as $index => $condition) {
            if(is_a($condition, $pClassName)) {
                array_splice($this->_conditions, $i, 1);
                $this->_concatSql();
                return $condition;
            }
            $i++;
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
