<?php


namespace Pressmind\ORM\Validator;


class Unsigned implements ValidatorInterface
{
    public function isValid($pValue)
    {
        return intval($pValue) >= 0;
    }

    public function getError()
    {
        return 'Given value is not unsigned';
    }
}
