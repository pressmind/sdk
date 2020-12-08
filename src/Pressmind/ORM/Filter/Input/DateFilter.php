<?php
namespace Pressmind\ORM\Filter\Input;
use Pressmind\ORM\Filter\FilterInterface;
use \DateTime;
use \stdClass;
use \Exception;
/**
 * Class DatetimeFilter
 * @package PressmindBooking\Filter\Input
 */
class DateFilter implements FilterInterface {

    private $_errors = [];

    /**
     * @param string|array|stdClass $pValue
     * @return bool|DateTime|null
     */
    public function filterValue($pValue)
    {
        if(empty($pValue) || is_a($pValue, 'DateTime')) return $pValue;
        try {
            $value = null;
            /**it might be that we receive a representation of a JSON DateTime Object, so we need to cover this case ...**/
            if((is_array($pValue) && isset($pValue['date'])) || (is_a($pValue, 'stdClass') && isset($pValue->date))) {
                if(is_array($pValue)) {
                    $pValue = $pValue['date'];
                } else {
                    $pValue = $pValue->date;
                }
                $value = DateTime::createFromFormat('Y-m-d', $pValue);
            } else {
                $value = DateTime::createFromFormat('Y-m-d', $pValue);
            }
            return $value;
        } catch (Exception $e) {
            $this->_errors[] = $e->getMessage();
            throw new \Exception('Could not convert ' . $pValue . ' to \DateTime');
        }
    }

    public function getErrors()
    {
        return $this->_errors;
    }
}
