<?php


namespace Pressmind\Cache\Adapter;


use DateTime;
use Pressmind\HelperFunctions;
use Pressmind\Log\Writer;
use Pressmind\Registry;
use Pressmind\REST\Server;
use Pressmind\Search;

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

    private $_ttl = null;

    public function __construct()
    {
        $this->_config = Registry::getInstance()->get('config')['cache'];
        $this->_server = new \Redis();
        $this->_server->connect($this->_config['adapter']['config']['host'], $this->_config['adapter']['config']['port']);
        if(isset($this->_config['adapter']['config']['password']) && !empty($this->_config['adapter']['config']['password'])) {
            $this->_server->auth($this->_config['adapter']['config']['password']);
        }
        $this->_ttl = $this->_config['max_idle_time'];
        $this->_prefix = HelperFunctions::replaceConstantsFromConfig($this->_config['key_prefix']);
    }

    public function isEnabled()
    {

    }

    public function flushAll($category = null)
    {
        $keys = $this->_server->keys('pm:' . $this->_prefix . ':' . $category . '*');
        foreach ($keys as $key) {
            $this->remove($key);
        }
    }

    public function add($pKey, $pValue, $info = null, $ttl = null)
    {
        $now = new \DateTime();
        $this->_server->hSet('pm:' . $this->_prefix.':info', 'pm:' . $this->_prefix . ':' . $pKey, json_encode($info));
        $this->_server->hSet('pm:' . $this->_prefix.':time', 'pm:' . $this->_prefix . ':' . $pKey, $now->format(\DateTime::ISO8601));
        return $this->_server->set('pm:' . $this->_prefix . ':' . $pKey, $pValue, is_null($ttl) ? $this->_ttl : $ttl);

    }

    public function get($pKey)
    {
        return $this->_server->get('pm:' . $this->_prefix . ':' . $pKey);
    }

    public function exists($pKey)
    {
        return $this->_server->exists('pm:' . $this->_prefix . ':' . $pKey);
    }

    public function remove($pKey)
    {
        $this->_server->hDel('pm:' . $this->_prefix.':info', $pKey);
        $this->_server->hDel('pm:' . $this->_prefix.':time', $pKey);
        $this->_server->hDel('pm:' . $this->_prefix.':idletime', $pKey);
        return $this->_server->del('pm:' . $this->_prefix . ':' . $pKey);
    }

    public function updateAll($category = null) {
        $keys = $this->_server->keys('pm:' . $this->_prefix . ':' . $category . '*');
        foreach ($keys as $key) {
            $this->update($key);
        }
    }

    public function update($pKey) {
        $info = $this->getInfo($pKey);
    }

    public function getKeys()
    {
        return $this->_server->keys('pm:' . $this->_prefix . ':*');
    }

    public function getInfo($pKey)
    {
        return [
            'key' => $pKey,
            'info' => json_decode($this->_server->hGet('pm:' . $this->_prefix.':info', 'pm:' . $this->_prefix . ':' . $pKey)),
            'date' => $this->_server->hGet('pm:' . $this->_prefix.':time', 'pm:' . $this->_prefix . ':' . $pKey),
            'idle' => $this->_server->hGet('pm:' . $this->_prefix.':idletime', 'pm:' . $this->_prefix . ':' . $pKey)
        ];
    }

    public function cleanUp()
    {
        $keys = $this->_server->keys('pm:' . $this->_prefix . ':*');
        $total_keys = count($keys);
        Writer::write('Found ' . $total_keys . ' keys in cache', WRITER::OUTPUT_BOTH, 'redis', WRITER::TYPE_INFO);
        $error = false;
        $i = 0;
        foreach ($keys as $key) {
            $i++;
            Writer::write($i . ' of ' . $total_keys . ' Checking key ' . $key, WRITER::OUTPUT_FILE, 'redis', WRITER::TYPE_INFO);
            $idle_time = $this->_server->object('idletime', $key);
            $last_idle_time = $this->_server->hGet('pm:' . $this->_prefix.':idletime', $key);
            $info = json_decode($this->_server->hGet('pm:' . $this->_prefix.':info', $key));
            $now = new DateTime();
            $cache_date = $this->_server->hGet('pm:' . $this->_prefix.':time', $key);
            $date = DateTime::createFromFormat(DateTime::ISO8601, $cache_date);
            $age = $now->getTimestamp() - $date->getTimestamp();
            if($idle_time - $last_idle_time > 0) {
                $idle_time = $idle_time + $age;
            }
            Writer::write('Idle time: ' . $idle_time . ' sec.', WRITER::OUTPUT_FILE, 'redis', WRITER::TYPE_INFO);
            Writer::write('Age: ' . $age . ' sec.', WRITER::OUTPUT_FILE, 'redis', WRITER::TYPE_INFO);
            if($idle_time >= $this->_config['max_idle_time'] || $age >= $this->_config['update_frequency']) {
                Writer::write('Deleting key ' . $key, WRITER::OUTPUT_FILE, 'redis', WRITER::TYPE_INFO);
                $this->_server->del($key);
                $this->_server->hDel('pm:' . $this->_prefix.':info', $key);
                $this->_server->hDel('pm:' . $this->_prefix.':time', $key);
                $this->_server->hDel('pm:' . $this->_prefix.':idletime', $key);
            }
            if($age >= $this->_config['update_frequency'] && $idle_time < $this->_config['max_idle_time'] && is_a($info, 'stdClass')) {
                Writer::write('Updating key ' . $key . ' due to update frequency', WRITER::OUTPUT_FILE, 'redis', WRITER::TYPE_INFO);
                Writer::write('Type: ' . $info->type, WRITER::OUTPUT_FILE, 'redis', WRITER::TYPE_INFO);
                Writer::write('Class: ' . $info->classname, WRITER::OUTPUT_FILE, 'redis', WRITER::TYPE_INFO);
                Writer::write('Method: ' . $info->method, WRITER::OUTPUT_FILE, 'redis', WRITER::TYPE_INFO);
                Writer::write('Params: ' . print_r($info->parameters, true), WRITER::OUTPUT_FILE, 'redis', WRITER::TYPE_INFO);
                try {
                    if (strtolower($info->type) == 'rest') {
                        $rest_server = new Server();
                        $rest_server->directCall($info->classname, $info->method, json_decode(json_encode($info->parameters), true));
                    }
                    if (strtolower($info->type) == 'search') {
                        $search = new Search();
                        $result = $search->updateCache($info->parameters);
                        Writer::write('Update returned: ' . $result, WRITER::OUTPUT_FILE, 'redis', WRITER::TYPE_INFO);
                    }
                    if (strtolower($info->type) == 'object') {
                        $classname = $info->classname;
                        $object = new $classname();
                        $result = $object->updateCache($info->parameters->id);
                        Writer::write('Update returned: ' . $result, WRITER::OUTPUT_FILE, 'redis', WRITER::TYPE_INFO);
                    }
                } catch (\Exception $e) {
                    Writer::write('Failed Updating key ' . $key . ': ' . $e->getMessage(), WRITER::OUTPUT_FILE, 'redis', WRITER::TYPE_ERROR);
                    $error = true;
                }
                $this->_server->hSet('pm:' . $this->_prefix.':idletime', $key, $idle_time);
            }
        }
        if($error == true) {
            throw new \Exception('Pressmind\Cache\Adapter\Redis::cleanUp() threw errors. See log category "redis" for more information');
        }
        return 'Task completed';
    }
}
