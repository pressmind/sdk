<?php


namespace Pressmind\ORM\Filter\Input;
use Pressmind\ORM\Filter\FilterInterface;

class BooleanFilter implements FilterInterface
{
    private $_errors = [];

    public function filterValue($pValue)
    {
        return boolval($pValue);
    }

    public function getErrors()
    {
        return $this->_errors;
    }
}
