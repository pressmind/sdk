<?php

namespace Pressmind\Search\Condition\MongoDB;

class DurationRange
{
    private $_durationFrom;

    private $_durationTo;

    public function __construct($durationFrom, $durationTo)
    {
        $this->_durationFrom = floatval($durationFrom);
        $this->_durationTo = floatval($durationTo.'.9');
    }

    /**
     * @return string
     */
    public function getType(){
        return (new \ReflectionClass($this))->getShortName();
    }

    /**
     * Get the from duration
     * 
     * @return float
     */
    public function getDurationFrom(): float
    {
        return $this->_durationFrom;
    }

    /**
     * Get the to duration
     * 
     * @return float
     */
    public function getDurationTo(): float
    {
        return $this->_durationTo;
    }

    public function getQuery($type = 'first_match')
    {
        if($type == 'first_match') {
            return ['prices' => ['$elemMatch' => ['duration' => ['$gte' => $this->_durationFrom, '$lte' => $this->_durationTo]]]];
        } else if($type == 'prices_filter') {
            return [
                ['$gte' => ['$$this.duration', $this->_durationFrom]],
                ['$lte' => ['$$this.duration', $this->_durationTo]],
            ];
        }
        return null;
    }
}
