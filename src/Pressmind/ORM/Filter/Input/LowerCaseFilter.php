<?php
namespace Pressmind\ORM\Filter\Input;
use Pressmind\ORM\Filter\FilterInterface;

class LowerCaseFilter implements FilterInterface
{
    private $_errors = [];

    public function filterValue($pValue)
    {
        if(empty($pValue) || $pValue === 'null') return null;
        if(is_object($pValue)) return 'Error: Object to string conversion';
        return strtolower($pValue);
    }

    public function getErrors()
    {
        return $this->_errors;
    }
}
