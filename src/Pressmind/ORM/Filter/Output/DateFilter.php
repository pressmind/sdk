<?php

namespace Pressmind\ORM\Filter\Output;

use Pressmind\ORM\Filter\FilterInterface;
use DateTime;

class DateFilter implements FilterInterface
{

    private $_errors = [];

    /**
     * @param DateTime $pValue
     * @return mixed|bool
     */
    public function filterValue($pValue)
    {
        if (empty($pValue)) return null;
        try {
            return $pValue->format('Y-m-d');
        } catch (\Exception $e) {
            echo $e->getMessage();
            return false;
        }
    }

    public function getErrors()
    {
        return $this->_errors;
    }
}
