<?php


namespace Pressmind\ORM\Filter\Output;
use Pressmind\ORM\Filter\FilterInterface;

class TimeFilter implements FilterInterface
{
    private $_errors = [];

    public function filterValue($pValue)
    {
        if(empty($pValue)) return null;
        try {
            return $pValue->format('H:i:s');
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function getErrors()
    {
        return $this->_errors;
    }
}
