<?php


namespace Pressmind\REST\Controller;


class Redis {

    /**
     * @var \Pressmind\Cache\Adapter\Redis
     */
    private $_adapter;

    public function __construct() {
        $this->_adapter = new \Pressmind\Cache\Adapter\Redis();
    }

    public function getKeyValue($parameters)
    {
        //return $parameters['key'];
        return json_decode($this->_adapter->get($parameters['key']));
    }

    public function getKeys()
    {
        $keys = $this->_adapter->getKeys();
        return ['total_key_count'=> count($keys), 'keys' => $keys];
    }

    public function getInfo($parameters)
    {
        return $this->_adapter->getInfo($parameters['key']);
    }
}

