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
        /** @var Search\Condition\PriceRange $price_condition */
        if($price_condition = $this->_search->getCondition(Search\Condition\PriceRange::class)) {
            $min = $price_condition->priceFrom;
            $max = $price_condition->priceTo;
        }
        $results = $this->_search->getResults();
        $prices = [];
        foreach ($results as $result) {
            $cheapest_price = $result->getCheapestPrice();
            if(!is_null($cheapest_price->price_total) && (($min == null || $max == null) || ($min <= $cheapest_price->price_total && $max >= $cheapest_price->price_total))) {
                $prices[] = $cheapest_price->price_total;
            }
        }
        sort($prices);
        $min_max_result = new MinMax();
        $min_max_result->min = $prices[0];
        $min_max_result->max = $prices[count($prices)-1];
        return $min_max_result;
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
