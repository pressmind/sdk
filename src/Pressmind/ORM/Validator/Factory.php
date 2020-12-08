<?php
namespace Pressmind\ORM\Validator;
/**
 * Class Fractory
 * @package Pressmind
 */
class Factory {

    /**
     * @param array $pValidator
     * @return ValidatorInterface
     */
    public static function create($pValidator) {
        $validatorname = ucfirst($pValidator['name']);
        $validatorclassname = 'Pressmind\\ORM\\Validator\\' . $validatorname;
        $params = null;
        if(isset($pValidator['params'])) {
            $params = $pValidator['params'];
        }
        return new $validatorclassname($params);
    }
}
