<?php


namespace Pressmind\ORM\Filter\Output;
use Pressmind\ORM\Filter\FilterInterface;

class TableFilter implements FilterInterface
{
    private $_errors = [];

    public function filterValue($pValue)
    {
        if(empty($pValue)) return null;
        return json_encode($pValue);
    }

    public function getErrors()
    {
        return $this->_errors;
    }
}
