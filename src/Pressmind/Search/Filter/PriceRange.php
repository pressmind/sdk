<?php


namespace Pressmind\Search\Filter;


use Exception;
use Pressmind\Registry;
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
        if(!$this->_search->getCondition(Search\Condition\PriceRange::class)) {
            $this->_search->addCondition('priceRange', new Search\Condition\PriceRange(1, 10000000));
        } else {
            $new_config = new \stdClass();
            $new_config->priceFrom = 1;
            $new_config->priceTo = 10000000;
            $this->_search->getCondition(Search\Condition\PriceRange::class)->setConfig($new_config);
        }
        $this->_search->removeLimits();
        $this->_search->removeSortProperties();
        $this->_search->return_id_only = true;
        $results = $this->_search->getResults(false, false);

        $ids = [];
        foreach ($results as $result) {
            $ids[] = $result->id;
        }
        if(!empty($result)) {
            $db = Registry::getInstance()->get('db');
            $minmaxResult = $db->fetchRow("SELECT MIN(price_total) as minPrice, MAX(price_total) as maxPrice FROM pmt2core_cheapest_price_speed WHERE id_media_object IN(" . implode(',', $ids) . ") AND price_total > 0 AND date_departure > '2021-06-22 00:00:00' AND (2 BETWEEN option_occupancy_min AND option_occupancy_max)");
            $minmax = new MinMax();
            $minmax->min = $minmaxResult->minPrice;
            $minmax->max = $minmaxResult->maxPrice;
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
