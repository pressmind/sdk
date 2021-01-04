<?php


namespace Pressmind\Config\Adapter;


use Pressmind\Config\AdapterInterface;

class Php implements AdapterInterface
{
    /**
     * @var string
     */
    private $_config_file;

    /**
     * @var string|null
     */
    private $_environment;

    /**
     * Php constructor.
     * @param string $name
     * @param string $environment
     * @param array $options
     */
    public function __construct($name, $environment = null, $options = array())
    {
        $this->_config_file = $name;
        $this->_environment = is_null($environment) ? 'development' : $environment;
    }

    /**
     * @return array
     */
    public function read()
    {
        /** @var array $config */

        $return_config = [
            'development' => [],
            'production' => [],
            'testing' => [],
        ];

        require_once $this->_config_file;
        $return_config['development'] = $config['development'];
        $return_config['production'] = array_merge($config['development'], $config['production']);
        $return_config['testing'] = array_merge($config['development'], $config['testing']);
        return $return_config[$this->_environment];
    }

    /**
     * @param array $data
     * @return void
     */
    public function write($data)
    {
        require_once $this->_config_file;
        /** @var array $config */
        $config[$this->_environment] = $data;
        file_put_contents($this->_config_file, var_export($config, true));
    }
}
