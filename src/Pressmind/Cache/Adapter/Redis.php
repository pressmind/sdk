<?php


namespace Pressmind\Cache\Adapter;


use DateTime;
use Pressmind\HelperFunctions;
use Pressmind\Log\Writer;
use Pressmind\Registry;
use Pressmind\REST\Server;

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

    /**
     * @var array
     */
    private $_config;

    public function __construct()
    {
        $this->_config = Registry::getInstance()->get('config')['cache'];
        $this->_server = new \Redis();
        $this->_server->connect($this->_config['adapter']['config']['host'], $this->_config['adapter']['config']['port']);
        $this->_prefix = HelperFunctions::replaceConstantsFromConfig($this->_config['key_prefix']);
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

    public function cleanUp()
    {
        $keys = $this->_server->keys('pmt2core-' . $this->_prefix . '-*');
        Writer::write('Found ' . count($keys) . ' keys in cache', WRITER::OUTPUT_BOTH, 'redis', WRITER::TYPE_INFO);
        foreach ($this->_server->keys('pmt2core-' . $this->_prefix . '-*') as $key) {
            Writer::write('Checking key ' . $key, WRITER::OUTPUT_BOTH, 'redis', WRITER::TYPE_INFO);
            $idle_time = $this->_server->object('idletime', $key);
            $info = json_decode($this->_server->hGet('pmt2corecacheinfo-' . $this->_prefix, $key));
            $now = new DateTime();
            $cache_date = $this->_server->hGet('pmt2corecachetime-' . $this->_prefix, $key);
            $date = DateTime::createFromFormat(DateTime::ISO8601, $cache_date);
            $age = $now->getTimestamp() - $date->getTimestamp();
            Writer::write('Idle time: ' . $idle_time . ' sec.', WRITER::OUTPUT_BOTH, 'redis', WRITER::TYPE_INFO);
            Writer::write('Age: ' . $age . ' sec.', WRITER::OUTPUT_BOTH, 'redis', WRITER::TYPE_INFO);
            if($idle_time >= $this->_config['max_idle_time'] || $age >= $this->_config['update_frequency']) {
                Writer::write('Deleting key ' . $key, WRITER::OUTPUT_BOTH, 'redis', WRITER::TYPE_INFO);
                $this->_server->del($key);
                $this->_server->hDel('pmt2corecacheinfo-' . $this->_prefix, $key);
                $this->_server->hDel('pmt2corecachetime-' . $this->_prefix, $key);
            }
            if($age >= $this->_config['update_frequency'] && $idle_time < $this->_config['max_idle_time']) {
                Writer::write('Updating key ' . $key . ' due to update frequency', WRITER::OUTPUT_BOTH, 'redis', WRITER::TYPE_INFO);
                Writer::write('Type: ' . $info->type, WRITER::OUTPUT_BOTH, 'redis', WRITER::TYPE_INFO);
                Writer::write('Class: ' . $info->classname, WRITER::OUTPUT_BOTH, 'redis', WRITER::TYPE_INFO);
                Writer::write('Method: ' . $info->method, WRITER::OUTPUT_BOTH, 'redis', WRITER::TYPE_INFO);
                Writer::write('Params: ' . print_r($info->parameters, true), WRITER::OUTPUT_BOTH, 'redis', WRITER::TYPE_INFO);
                if(strtolower($info->type) == 'rest') {
                    $rest_server = new Server();
                    $rest_server->directCall($info->classname, $info->method, json_decode(json_encode($info->parameters), true));
                }
            }
        }
    }
}
