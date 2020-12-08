<?php
namespace Pressmind\ORM\Filter\Output;
use Pressmind\ORM\Filter\FilterInterface;
use DateTime;
class DatetimeFilter implements FilterInterface {

    private $_errors = [];

    /**
     * @param DateTime $pValue
     * @return mixed|bool
     */
    public function filterValue($pValue)
    {
        if(empty($pValue)) return null;
        try {
            return $pValue->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function getErrors()
    {
        return $this->_errors;
    }
}
