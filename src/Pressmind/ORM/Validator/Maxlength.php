<?php


namespace Pressmind\ORM\Validator;


class Maxlength implements ValidatorInterface
{
    public function isValid($pValue)
    {
        return true;
    }

    public function getError()
    {
        return 'Error Message';
    }
}
