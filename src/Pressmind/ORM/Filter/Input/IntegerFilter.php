<?php


namespace Pressmind\ORM\Filter\Input;
use Pressmind\ORM\Filter\FilterInterface;

class IntegerFilter implements FilterInterface
{
    private $_errors = [];

    public function filterValue($pValue)
    {
        if($pValue == '') {
            return null;
        }
        return intval($pValue);
    }

    public function getErrors()
    {
        return $this->_errors;
    }
}
