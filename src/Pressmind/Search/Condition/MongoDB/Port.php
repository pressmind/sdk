<?php

namespace Pressmind\Search\Condition\MongoDB;

class Port
{
    /**
     * @var boolean
     */
    private $_ports;

    /**
     * @param boolean $sold_out
     */
    public function __construct($port)
    {
        if(!is_array($port)) {
            $port = [$port];
        }
        $this->_ports = $port;
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
            return ['ports.id' => ['$in' => $this->_ports]];
        }
        return null;
    }
}
