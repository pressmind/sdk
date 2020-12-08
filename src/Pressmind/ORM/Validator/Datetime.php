<?php


namespace Pressmind\ORM\Validator;

/**
 * Class Datetime
 * @package Pressmind\ORM\Validator
 */
class Datetime implements ValidatorInterface
{

    /**
     * @var array
     */
    private $params;

    /**
     * @var mixed
     */
    private $value;

    /**
     * Datetime constructor.
     * @param array $params
     */
    public function __construct($params)
    {
        $this->params = $params;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    public function isValid($value)
    {
        $this->value = $value;
        if((is_array($value) && isset($value['date'])) || (is_a($value, 'stdClass') && isset($value->date)) || is_a($value, 'DateTime') || $this->validateDate($value) !== false) {
            return true;
        }
        return false;
    }

    /**
     * @param string $date
     * @param string $format
     * @return bool
     */
    private function validateDate($date, $format = 'Y-m-d H:i:s'){
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * @return string
     */
    public function getError()
    {
        return 'Given value "' . $this->value . '" is not a valid DateTime Representation (\DateTime object OR string in format Y-m-d h:i:s';
    }
}
