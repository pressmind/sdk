<?php

namespace Pressmind\Search\Condition\MongoDB;

class Guaranteed
{
    /**
     * @var boolean
     */
    private $_guaranteed;

    /**
     * @param boolean $sold_out
     */
    public function __construct($guaranteed)
    {
        $this->_guaranteed = $guaranteed;
    }

    /**
     * @return string
     */
    public function getType(){
        return (new \ReflectionClass($this))->getShortName();
    }

    public function getQuery($type = 'first_match')
    {
        if($type == 'first_match') {
            return ['has_guaranteed_departures' => $this->_guaranteed];
        }
        return null;
    }
}
