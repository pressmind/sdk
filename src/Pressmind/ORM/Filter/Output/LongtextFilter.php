<?php


namespace Pressmind\ORM\Filter\Output;
use Pressmind\ORM\Filter\FilterInterface;

class LongtextFilter implements FilterInterface
{
    private $_errors = [];

    public function filterValue($pValue)
    {
        if(empty($pValue) || $pValue === 'null') return null;
        if(is_object($pValue)) return 'Error: Object to string conversion';
        return $pValue;
    }

    public function getErrors()
    {
        return $this->_errors;
    }
}
