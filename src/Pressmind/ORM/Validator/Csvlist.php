<?php

namespace Pressmind\ORM\Validator;

class Csvlist implements ValidatorInterface
{
    private $_error;
    private $_params;

    public function __construct($params = null)
    {
        $this->_params = $params;
    }

    public function isValid($pValue)
    {
        $pattern = $this->_params ?? '/^[a-zA-Z0-9]+(,[a-zA-Z0-9]+)*$/';
        if (!preg_match($pattern, $pValue)) {
            $this->_error = 'Value must be a comma-separated list, got: "' . $pValue . '"';
            return false;
        }
        return true;
    }

    public function getError()
    {
        return $this->_error;
    }
}
