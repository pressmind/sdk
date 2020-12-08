<?php


namespace Pressmind\ORM\Filter\Output;
use Pressmind\ORM\Filter\FilterInterface;

class IntegerFilter implements FilterInterface
{
    private $_errors = [];

    public function filterValue($pValue)
    {
        return intval($pValue);
    }

    public function getErrors()
    {
        return $this->_errors;
    }
}
