<?php


namespace Pressmind\Search\Filter;


use Exception;
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
        /** @var Search\Condition\DurationRange $duration_condition */
        if($duration_condition = $this->_search->getCondition(Search\Condition\DurationRange::class)) {
            $min = $duration_condition->durationFrom;
            $max = $duration_condition->durationTo;
        }
        $results = $this->_search->getResults();
        $durations = [];
        foreach ($results as $result) {
            foreach ($result->booking_packages as $booking_package) {
                if(($min == null || $max == null) || ($min <= $booking_package->duration && $max >= $booking_package->duration)) {
                    $durations[] = $booking_package->duration;
                }
            }
        }
        sort($durations);
        $min_max_result = new MinMax();
        $min_max_result->min = $durations[0];
        $min_max_result->max = $durations[count($durations)-1];
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
