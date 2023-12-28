<?php


namespace Pressmind\ORM\Validator;


class Precision implements ValidatorInterface
{

    private $_error;

    public function isValid($pValue)
    {
        if(!is_float($pValue)) {
            $this->_error = 'Float validation failed';
            return false;
        }
        return true;
    }

    public function getError()
    {
        return $this->_error;
    }
}
