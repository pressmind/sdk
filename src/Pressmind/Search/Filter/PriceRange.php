<?php


namespace Pressmind\Search\Filter;


use Exception;
use Pressmind\Search;
use Pressmind\ValueObject\Search\Filter\Result\MinMax;

class PriceRange implements FilterInterface
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

        $this->_search->removeCondition(Search\Condition\PriceRange::class);
        $results = $this->_search->getResults(false, true);
        $prices = [];
        foreach ($results as $result) {
            $cheapest_price_filter = null;
            /** @var Search\Condition\HousingOption $housing_option_condition */
            if($housing_option_condition = $this->_search->getCondition('\Pressmind\Search\Condition\HousingOption')) {
                $cheapest_price_filter = new Search\CheapestPrice();
                $cheapest_price_filter->occupancy = $housing_option_condition->occupancy;
            }
            $cheapest_price = $result->getCheapestPrice($cheapest_price_filter);
            if(!is_null($cheapest_price) && (($min == null || $max == null) || ($min <= $cheapest_price->price_total && $max >= $cheapest_price->price_total))) {
                $prices[] = $cheapest_price->price_total;
            }
        }
        if(count($prices) > 0) {
            sort($prices);
            $min_max_result = new MinMax();
            $min_max_result->min = $prices[0];
            $min_max_result->max = $prices[count($prices) - 1];
            return $min_max_result;
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
