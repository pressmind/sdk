<?php

namespace Pressmind\ORM\Filter\Input;

use Pressmind\ORM\Filter\FilterInterface;

class JsonFilter implements FilterInterface
{
    private $_errors = [];

    public function filterValue($pValue)
    {
        if ($pValue === null || $pValue === '' || $pValue === 'null') {
            return null;
        }
        if (is_array($pValue)) {
            return $this->normalize($pValue);
        }
        if (is_object($pValue)) {
            return $this->normalize($pValue);
        }
        if (!is_string($pValue)) {
            $this->_errors[] = 'JSON value must be an array, object, string, or null';
            return null;
        }

        $decoded = json_decode($pValue, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->_errors[] = json_last_error_msg();
            return null;
        }
        return $decoded;
    }

    public function getErrors()
    {
        return $this->_errors;
    }

    private function normalize($value)
    {
        $encoded = json_encode($value);
        if ($encoded === false) {
            $this->_errors[] = json_last_error_msg();
            return null;
        }
        return json_decode($encoded, true);
    }
}
