<?php

namespace Pressmind\REST\Controller;

/**
 * Redis cache inspection. All endpoints require both API key and Basic Auth.
 */
class Redis
{
    use RequireApiKeyAndBasicAuthTrait;

    /**
     * @var \Pressmind\Cache\Adapter\Redis
     */
    private $_adapter;

    public function __construct()
    {
        $this->_adapter = new \Pressmind\Cache\Adapter\Redis();
    }

    public function getKeyValue($parameters)
    {
        $this->requireApiKeyAndBasicAuth($parameters);
        return json_decode($this->_adapter->get($parameters['key']));
    }

    public function getKeys($parameters = [])
    {
        $this->requireApiKeyAndBasicAuth($parameters);
        $keys = $this->_adapter->getKeys();
        return ['total_key_count' => count($keys), 'keys' => $keys];
    }

    public function getInfo($parameters)
    {
        $this->requireApiKeyAndBasicAuth($parameters);
        return $this->_adapter->getInfo($parameters['key']);
    }
}

