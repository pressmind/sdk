<?php

namespace Pressmind\Search\Condition\MongoDB;

class TransportType
{
    private $_transportType;

    public function __construct($transportType)
    {
        $this->_transportType = !is_array($transportType) ? [$transportType] : $transportType;
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
            return ['prices' => ['$elemMatch' => ['transport_type' => ['$in' => $this->_transportType]]]];
        }
        return null;
    }
}
