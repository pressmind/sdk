<?php


namespace Pressmind;


use Pressmind\Config\AdapterInterface;

/**
 * Class Config
 * @package Pressmind
 */
class Config
{
    /**
     * @var AdapterInterface
     */
    private $_adapter;

    /**
     * Config constructor.
     * @param string $adapter
     * @param string $name
     * @param string $environment
     * @param array $options
     */
    public function __construct($adapter, $name, $environment, $options = [])
    {
        $adapterClass = '\\Pressmind\\Config\\Adapter\\' . ucfirst($adapter);
        $this->_adapter = new $adapterClass($name, $environment, $options);
    }

    /**
     * @return array
     */
    public function read()
    {
        return $this->_adapter->read();
    }

    public function write($data) {
        $this->_adapter->write($data);
    }
}
