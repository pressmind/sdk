<?php


namespace Pressmind\Cache\Adapter;


use Pressmind\HelperFunctions;
use Pressmind\Registry;

class Redis implements AdapterInterface
{

    /**
     * @var \Redis
     */
    private $_server;

    /**
     * @var string
     */
    private $_prefix;

    public function __construct()
    {
        $config = Registry::getInstance()->get('config');
        $this->_server = new \Redis();
        $this->_server->connect($config['cache']['adapter']['config']['host'], $config['cache']['adapter']['config']['port']);
        $this->_prefix = HelperFunctions::replaceConstantsFromConfig($config['cache']['key_prefix']);
    }

    public function isEnabled()
    {

    }

    public function flushAll()
    {
        $this->_server->flushAll();
    }

    public function add($pKey, $pValue, $info = null)
    {
        $now = new \DateTime();
        $this->_server->hSet('pmt2corecacheinfo-' . $this->_prefix, 'pmt2core-' . $this->_prefix . '-' . $pKey, json_encode($info));
        $this->_server->hSet('pmt2corecachetime-' . $this->_prefix, 'pmt2core-' . $this->_prefix . '-' . $pKey, $now->format(\DateTime::ISO8601));
        return $this->_server->set('pmt2core-' . $this->_prefix . '-' . $pKey, $pValue);

    }

    public function get($pKey)
    {
        return $this->_server->get('pmt2core-' . $this->_prefix . '-' . $pKey);
    }

    public function exists($pKey)
    {
        return $this->_server->exists('pmt2core-' . $this->_prefix . '-' . $pKey);
    }

    public function remove($pKey)
    {
        return $this->_server->del('pmt2core-' . $this->_prefix . '-' . $pKey);
    }
}
