<?php


namespace Pressmind\Config;


interface AdapterInterface
{

    /**
     * AdapterInterface constructor.
     * @param string $name
     * @param string $environment
     * @param array $options
     */
    public function __construct($name, $environment = null, $options = array());

    /**
     * @return array
     */
    public function read();

    /**
     * @param array $data
     * @return void
     */
    public function write($data);
}
