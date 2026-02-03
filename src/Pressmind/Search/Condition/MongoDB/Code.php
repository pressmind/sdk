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
            if (count($this->_codes) > 1) {
                $q = [];
                foreach ($this->_codes as $code) {
                    if($this->_asRegex) {
                        $q[] = ['code' => ['$regex' => $code]];
                    }else{
                        $q[] = ['code' => $code];
                    }
                }
                $query['$' . strtolower($this->_combineOperator)] = $q;
            } else {
                if($this->_asRegex) {
                    $query['code'] = ['$regex' => $this->_codes[0]];
                }else{
                    $query['code'] = $this->_codes[0];
                }

            }
            return $query;
        }
        return null;
    }
}
