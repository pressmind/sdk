<?php
namespace Pressmind\ORM\Validator;

interface ValidatorInterface {
    public function isValid($pValue);
    public function getError();
}
