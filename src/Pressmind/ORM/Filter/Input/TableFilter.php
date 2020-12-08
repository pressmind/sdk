<?php


namespace Pressmind\ORM\Filter\Input;
use Pressmind\ORM\Filter\FilterInterface;

class TableFilter implements FilterInterface
{
    private $_errors = [];

    public function filterValue($pValue)
    {
        if(empty($pValue)) return null;
        if(is_array($pValue)) return $pValue;
        return json_decode($pValue, true);
    }

    public function getErrors()
    {
        return $this->_errors;
    }
}
