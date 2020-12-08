<?php


namespace Pressmind\ORM\Filter\Output;
use Pressmind\ORM\Filter\FilterInterface;

class BooleanFilter implements FilterInterface
{
    private $_errors = [];

    /**
     * @param boolean $pValue
     * @return int
     */
    public function filterValue($pValue)
    {
        return intval($pValue);
    }

    public function getErrors()
    {
        return $this->_errors;
    }
}
