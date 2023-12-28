<?php


namespace Pressmind\ORM\Filter\Output;
use Pressmind\ORM\Filter\FilterInterface;

class DecimalFilter implements FilterInterface
{
    private $_errors = [];

    public function filterValue($pValue)
    {
        return floatval($pValue);
    }

    public function getErrors()
    {
        return $this->_errors;
    }
}
