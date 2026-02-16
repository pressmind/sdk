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

    /**
     * Read config for all environments (development, production, testing).
     * Used for diff view. Keys: development, production, testing; each value is the full merged config for that env.
     *
     * @return array<string, array<string, mixed>>
     */
    public function readAllEnvironments();
}
