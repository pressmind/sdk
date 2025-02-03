<?php

namespace Pressmind\Search\Condition\MongoDB;

class SoldOut
{
    /**
     * @var boolean
     */
    private $_sold_out;

    /**
     * @param boolean $sold_out
     */
    public function __construct($sold_out)
    {
        $this->_sold_out = $sold_out;
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
            return ['sold_out' => $this->_sold_out];
        }
        return null;
    }
}
