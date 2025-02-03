<?php

namespace Pressmind\Search\Condition\MongoDB;

class SalesPriority
{
    /**
     * @var string
     */
    private $_sales_priority;

    /**
     * @param string $sales_priority
     */
    public function __construct($sales_priority)
    {
        $this->_sales_priority = $sales_priority;
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
            return ['sales_priority' => $this->_sales_priority];
        }
        return null;
    }
}
