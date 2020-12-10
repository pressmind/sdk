<?php


namespace Pressmind\Cache\Adapter;


use Pressmind\Registry;

class Redis implements AdapterInterface
{

    /**
     * @var \Redis
     */
    private $_server;

    public function __construct()
    {
        $config = Registry::getInstance()->get('config');
        $this->_server = new \Redis();
        $this->_server->connect($config['cache']['adapter']['config']['host'], $config['cache']['adapter']['config']['port']);
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
