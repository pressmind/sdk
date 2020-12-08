<?php


namespace Pressmind\ORM\Validator;


class Inarray implements ValidatorInterface
{

    private $params;
    private $value;

    public function __construct($params)
    {
        $this->params = $params;
    }

    public function isValid($value)
    {
        $this->value = $value;
        if(in_array($value, $this->params)) {
            return true;
        }
    }

    public function getError()
    {
        return 'Given value "' . $this->value . '" is not in scope of the required values ' . implode(', ', $this->params);
    }
}
