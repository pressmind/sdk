<?php


namespace Pressmind\ORM\Filter\Output;


use Pressmind\ORM\Filter\FilterInterface;

class ComputedFilter implements FilterInterface
{
    private $_errors = [];

    public function filterValue($pValue)
    {
        return $pValue;
    }

    public function getErrors()
    {
        return $this->_errors;
    }
}
