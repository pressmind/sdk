<?php


namespace Pressmind\ORM\Filter\Output;
use Pressmind\ORM\Filter\FilterInterface;

class DoubleFilter implements FilterInterface
{
    private $_errors = [];

    public function filterValue($pValue)
    {
        return doubleval($pValue);
    }

    public function getErrors()
    {
        return $this->_errors;
    }
}
