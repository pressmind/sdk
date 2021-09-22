<?php


namespace Pressmind\Search\Filter;


use Pressmind\DB\Adapter\Pdo;
use Pressmind\Registry;
use Pressmind\Search;
use Pressmind\ValueObject\Search\Filter\Result\DateRange;
use Pressmind\ValueObject\Search\Filter\Result\MinMax;
use stdClass;

class DepartureDate implements FilterInterface
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
        $this->setSearch($search);
        $this->_cache_enabled = $config['cache']['enabled'] && in_array('SEARCH_FILTER', $config['cache']['types']);
    }

    /**
     * @return Search
     */
    public function getSearch()
    {
        return $this->_search;
    }

    /**
     * @param Search $search
     * @return void
     */
    public function setSearch($search)
    {
        $this->_search = $search;
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        /** @var Search\Condition\DateRange $daterange_condition */
        if($daterange_condition = $this->_search->getCondition(Search\Condition\DateRange::class)) {
            $earliest_departure_date = $daterange_condition->dateFrom;
            $latest_departure_date = $daterange_condition->dateTo;
            $date_range = new DateRange();
            $date_range->from = $earliest_departure_date;
            $date_range->to = $latest_departure_date;
            return $date_range;
        } else {
            $results = $this->_search->getResults(false, false);
            $media_object_ids = [];
            foreach ($results as $result) {
                $media_object_ids[] = $result->id;
            }
            $query = [];
            $query[] = "SELECT min(date_departure) as earliest_date_departure, max(date_departure) as latest_date_departure from pmt2core_cheapest_price_speed WHERE id_media_object in(" . implode(',', $media_object_ids) . ")";
            /** @var Search\Condition\HousingOption $housing_option_condition */
            if($housing_option_condition = $this->_search->getCondition('\Pressmind\Search\Condition\HousingOption')) {
                $cheapest_price_filter = new Search\CheapestPrice();
                $cheapest_price_filter->occupancies = $housing_option_condition->occupancies;
                foreach ($housing_option_condition->occupancies as $occupancy) {
                    $query[] = "AND (" . $occupancy . " BETWEEN option_occupancy_min AND option_occupancy_max OR option_occupancy = " . $occupancy. ")";
                }
            }
            //$query[] = "ORDER BY date_departure ASC";
            $this->_sql = implode(' ', $query);
            $this->_values = [];
            if($this->_cache_enabled) {
                $cache_adapter = \Pressmind\Cache\Adapter\Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
                $key = 'SEARCH_FILTER_DEPARTUREDATE_' . md5($this->_sql . implode('', $this->_values));
                if($cache_adapter->exists($key)) {
                    $dates = json_decode($cache_adapter->get($key));
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
                    $dates = $db->fetchRow($this->_sql, $this->_values);
                    $cache_adapter->add($key, json_encode($dates), $info);
                }
            } else {
                $dates = $db->fetchRow($this->_sql, $this->_values);
            }
            if(is_null($dates)){
                return null;
            }
            $date_range = new DateRange();
            $date_range->from = new \DateTime($dates->earliest_date_departure);
            $date_range->to = new \DateTime($dates->latest_date_departure);
            return $date_range;
            /*if (count($dates) > 0) {
                $counter = count($dates) - 1;
                $earliest_departure_date = \DateTime::createFromFormat('Y-m-d H:i:s', $dates[0]->date_departure);
                $latest_departure_date = \DateTime::createFromFormat('Y-m-d H:i:s', $dates[$counter]->date_departure);
                $date_range = new DateRange();
                $date_range->from = $earliest_departure_date;
                $date_range->to = $latest_departure_date;
                return $date_range;
            }*/
        }
        return null;
    }

    /**
     * @param $search
     * @return DepartureDate
     */
    public static function create($search) {
        return new self($search);
    }

    /**
     * @param stdClass $config
     * @return mixed
     */
    public function setConfig($config)
    {
        // TODO: Implement setConfig() method.
    }
}
