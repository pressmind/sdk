<?php

namespace Pressmind\ORM\Filter\Output;

use Pressmind\ORM\Filter\FilterInterface;

class JsonFilter implements FilterInterface
{
    private $_errors = [];

    public function filterValue($pValue)
    {
        if ($pValue === null || $pValue === '' || $pValue === 'null') {
            return null;
        }

        if (is_string($pValue)) {
            json_decode($pValue);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $pValue;
            }
        }

        $encoded = json_encode($pValue);
        if ($encoded === false) {
            $this->_errors[] = json_last_error_msg();
            return null;
        }
        return $encoded;
    }

    public function getErrors()
    {
        return $this->_errors;
    }
}
