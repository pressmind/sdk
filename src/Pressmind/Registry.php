<?php

namespace Pressmind;
/**
 * Class Registry
 * Singleton implementation of a registry that holds global accessible data for the runtime of the application
 * @package Pressmind
 */
class Registry
{

    /**
     * @var array
     */
    private $_registry = [];

    /**
     * @var null|Registry
     */
    static $_instance = null;

    /**
     * Registry constructor.
     * Disable direct instantiation
     */
    protected function __construct()
    {
    }

    /**
     * Disable cloning
     */
    protected function __clone()
    {
    }

    /**
     * @return Registry
     */
    public static function getInstance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Add a new element to the registry
     * @param string $key
     * @param mixed $value
     */
    public function add($key, $value)
    {
        $this->_registry[$key] = $value;
    }

    /**
     * Remove an element from the registry
     * @param string $key
     */
    public function remove($key)
    {
        unset($this->_registry[$key]);
    }

    /**
     * Get a registry element
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->_registry[$key];
    }

    /**
     * Reset the instance
     */
    public static function clear()
    {
        self::$_instance = null;
    }
}
