<?php

namespace Pressmind\Search\Condition\MongoDB;

class Running
{
    /**
     * @var boolean
     */
    private $_is_running;

    /**
     * @param boolean $sold_out
     */
    public function __construct($is_running)
    {
        $this->_is_running = $is_running;
    }

    /**
     * @return string
     */
    public function getType(){
        return (new \ReflectionClass($this))->getShortName();
    }


    /**
     * TODO: not tested with production data
     * @param $type
     * @return bool[]|null
     */
    public function getQuery($type = 'first_match')
    {
        if($type == 'first_match') {
            return ['is_running' => $this->_is_running];
        }
        return null;
    }
}
