<?php

namespace Pressmind\Search\Condition\MongoDB;

class Code
{
    private $_codes;

    private $_combineOperator;

    private $_asRegex;

    public function __construct($codes, $combineOperator = 'OR', $asRegex = false)
    {
        if(!is_array($codes)) {
            $codes = [$codes];
        }
        $this->_asRegex = $asRegex;
        $this->_codes = $codes;
        $this->_combineOperator = $combineOperator;
    }

    /**
     * @return string
     */
    public function getType(){
        return (new \ReflectionClass($this))->getShortName();
    }

    /**
     * Get the codes array
     * 
     * @return array
     */
    public function getCodes(): array
    {
        return $this->_codes;
    }

    /**
     * Get the combine operator
     * 
     * @return string
     */
    public function getCombineOperator(): string
    {
        return $this->_combineOperator;
    }

    public function getQuery($type = 'first_match')
    {
        $query = [];
        if($type == 'first_match') {
            if($this->_asRegex) {
                if (count($this->_codes) > 1) {
                    $q = [];
                    foreach ($this->_codes as $code) {
                        $q[] = ['code' => ['$regex' => $code]];
                    }
                    $query['$' . strtolower($this->_combineOperator)] = $q;
                } else {
                    $query['code'] = ['$regex' => $this->_codes[0]];
                }
            } else {
                // Use $in operator - works for both scalar and array fields
                $query['code'] = ['$in' => $this->_codes];
            }
            return $query;
        }
        return null;
    }
}
