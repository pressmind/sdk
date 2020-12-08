<?php


namespace Pressmind\ORM\Validator;


class StringValidator implements ValidatorInterface
{

    private $_error;

    public function isValid($pValue)
    {
        if(!is_string($pValue)) {
            $this->_error = 'String validation failed';
            return false;
        }
        return true;
    }

    public function getError()
    {
        return $this->_error;
    }
}
