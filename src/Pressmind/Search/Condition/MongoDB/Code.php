<?php

namespace Pressmind\Search\Condition\MongoDB;

class Code
{
    private $_codes;

    private $_combineOperator;

    public function __construct($codes, $combineOperator = 'OR')
    {
        if(!is_array($codes)) {
            $codes = [$codes];
        }
        $this->_codes = $codes;
        $this->_combineOperator = $combineOperator;
    }

    /**
     * @return string
     */
    public function getType(){
        return (new \ReflectionClass($this))->getShortName();
    }

    public function getQuery($type = 'first_match')
    {
        if($type == 'first_match') {
            if (count($this->_codes) > 1) {
                $foo = [];
                foreach ($this->_codes as $code) {
                    $foo[] = ['code' => $code];
                }
                $query['$' . strtolower($this->_combineOperator)] = $foo;
            } else {
                $query['code'] = $this->_codes[0];
            }
            return $query;
        }
        return null;
    }
}
