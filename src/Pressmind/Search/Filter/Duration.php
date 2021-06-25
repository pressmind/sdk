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


    public function __construct($search = null)
    {
        $this->_search = $search;
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
        $min = null;
        $max = null;
        $this->_search->removeCondition(Search\Condition\DurationRange::class);
        $this->_search->removeLimits();
        $this->_search->removeSortProperties();
        $this->_search->return_id_only = true;
        $results = $this->_search->getResults(false, true);
        $durations = [];
        /*foreach ($results as $result) {
            foreach ($result->booking_packages as $booking_package) {
                if(($min == null || $max == null) || ($min <= $booking_package->duration && $max >= $booking_package->duration)) {
                    $durations[] = $booking_package->duration;
                }
            }
            $cheapest_price_filter = null;

            if($housing_option_condition = $this->_search->getCondition('\Pressmind\Search\Condition\HousingOption')) {
                $cheapest_price_filter = new Search\CheapestPrice();
                $cheapest_price_filter->occupancies = $housing_option_condition->occupancies;
            }
            $cheapest_price = null;//$result->getCheapestPrice($cheapest_price_filter);
            if(!is_null($cheapest_price) && (($min == null || $max == null) || ($min <= $cheapest_price->price_total && $max >= $cheapest_price->price_total))) {
                $durations[] = $cheapest_price->duration;
            }
        }
        if(count($durations) > 0) {
            sort($durations);
            $min_max_result = new MinMax();
            $min_max_result->min = $durations[0];
            $min_max_result->max = $durations[count($durations) - 1];
            return $min_max_result;
        }*/

        $ids = [];
        foreach ($results as $result) {
            $ids[] = $result->id;
        }
        if(!empty($ids)) {
            $db = Registry::getInstance()->get('db');
            $minmaxResult = $db->fetchRow("SELECT MIN(duration) as minDuration, MAX(duration) as maxDuration FROM pmt2core_cheapest_price_speed WHERE id_media_object IN(" . implode(',', $ids) . ") AND price_total > 0 AND date_departure > '2021-06-22 00:00:00' AND (2 BETWEEN option_occupancy_min AND option_occupancy_max)");
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
