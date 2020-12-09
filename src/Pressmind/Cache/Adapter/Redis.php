<?php


namespace Pressmind\Cache\Adapter;


class Redis implements AdapterInterface
{

    /**
     * @var \Redis
     */
    private $_server;

    public function __construct()
    {
        $this->_server = new \Redis();
        $this->_server->connect('127.0.0.1', 6379);
    }

    public function add($pKey, $pValue)
    {
        return $this->_server->set($pKey, $pValue);
    }

    public function get($pKey)
    {
        return $this->_server->get($pKey);
    }

    public function exists($pKey)
    {
        return $this->_server->exists($pKey);
    }

    public function remove($pKey)
    {
        return $this->_server->del($pKey);
    }
}
