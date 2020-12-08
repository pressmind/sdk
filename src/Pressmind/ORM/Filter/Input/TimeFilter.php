<?php


namespace Pressmind\ORM\Filter\Input;
use Pressmind\ORM\Filter\FilterInterface;
use \DateTime;
class TimeFilter implements FilterInterface
{
    private $_errors = [];

    public function filterValue($pValue)
    {
        if(empty($pValue)) return $pValue;
        try {
            $value = null;
            /**it might be that we receive a representation of a JSON DateTime Object, so we need to cover this case ...**/
            if((is_array($pValue) && isset($pValue['date'])) || (is_a($pValue, 'stdClass') && isset($pValue->date))) {
                if(is_array($pValue)) {
                    $pValue = $pValue['date'];
                } else {
                    $pValue = $pValue->date;
                }
                $value = DateTime::createFromFormat('Y-m-d H:i:s.000000', $pValue);
            } else {
                $value = DateTime::createFromFormat('Y-m-d H:i:s', $pValue);
            }
            return $value;
        } catch (Exception $e) {
            $this->_errors[] = $e->getMessage();
        }
    }

    public function getErrors()
    {
        return $this->_errors;
    }
}
