<?php


namespace Pressmind\Search\Filter;


use Exception;
use Pressmind\Registry;
use Pressmind\Search;
use Pressmind\ValueObject\Search\Filter\Result\MinMax;

class Duration implements FilterInterface
{
    /**
     * @var Search
     */
    private $_search;

    private $_sql;

    private $_values;

    private $_cache_enabled;

    public function __construct($search = null)
    {
        $config = Registry::getInstance()->get('config');
        $this->_search = $search;
        $this->_cache_enabled = $config['cache']['enabled'] && in_array('SEARCH_FILTER', $config['cache']['types']);
    }

    public function getSearch()
    {
        return $this->_search;
    }

    /**
     * @return MinMax
     * @throws Exception
     */
    public function getResult()
    {
        if(!$this->_search->getCondition(Search\Condition\DurationRange::class)) {
            $this->_search->addCondition('durationRange', new Search\Condition\DurationRange(1, 10000000));
        } else {
            $new_config = new \stdClass();
            $new_config->durationFrom = 1;
            $new_config->durationTo = 10000000;
            $this->_search->getCondition(Search\Condition\DurationRange::class)->setConfig($new_config);
        }
        $this->_search->removeLimits();
        $this->_search->removeSortProperties();
        $this->_search->return_id_only = true;
        $results = $this->_search->getResults(false, false);
        $ids = [];
        foreach ($results as $result) {
            $ids[] = $result->id;
        }
        if(!empty($ids)) {
            $db = Registry::getInstance()->get('db');
            $this->_values = [];
            $this->_sql = "SELECT MIN(duration) as minDuration, MAX(duration) as maxDuration FROM pmt2core_cheapest_price_speed WHERE id_media_object IN(" . implode(',', $ids) . ") AND price_total > 0 AND date_departure > NOW() AND (2 BETWEEN option_occupancy_min AND option_occupancy_max)";
            if($this->_cache_enabled) {
                $cache_adapter = \Pressmind\Cache\Adapter\Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
                $key = 'SEARCH_FILTER_DURATION_' . md5($this->_sql . implode('', $this->_values));
                if($cache_adapter->exists($key)) {
                    $minmaxResult = json_decode($cache_adapter->get($key));
                } else {
                    $info = [
                        'type' => 'SEARCH_FILTER',
                        'method' => 'updateCache',
                        'classname' => self::class,
                        'parameters' => [
                            'sql' => $this->_sql,
                            'values' => $this->_values
                        ]
                    ];
                    $minmaxResult = $db->fetchRow($this->_sql, $this->_values);
                    $cache_adapter->add($key, json_encode($minmaxResult), $info);
                }
            } else {
                $minmaxResult = $db->fetchRow($this->_sql, $this->_values);
            }
            $minmax = new MinMax();
            $minmax->min = $minmaxResult->minDuration;
            $minmax->max = $minmaxResult->maxDuration;
            return $minmax;
        }
        return new MinMax();
    }

    public static function create($search) {
        return new self($search);
    }

    public function setSearch($search) {
        $this->_search = $search;
    }

    public function setConfig($config) {

    }
}
