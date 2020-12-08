<?php


namespace Pressmind\Config\Adapter;


use Pressmind\Config\AdapterInterface;

class Json implements AdapterInterface
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
     * Json constructor.
     * @param string $fileName
     * @param null $environment
     * @param array $options
     */
    public function __construct($fileName, $environment = null, $options = [])
    {
        $this->_config_file = $fileName;
        $this->_environment = is_null($environment) ? 'development' : $environment;
    }

    /**
     * @return array
     */
    public function read()
    {
        $config = [
            'development' => [],
            'production' => [],
            'testing' => [],
        ];
        $tmp_config = json_decode(file_get_contents($this->_config_file), true);
        $config['development'] = $tmp_config['development'];
        $config['production'] = array_merge($tmp_config['development'], $tmp_config['production']);
        $config['testing'] = array_merge($tmp_config['development'], $tmp_config['testing']);
        return $config[$this->_environment];
    }

    /**
     * @param array $data
     */
    public function write($data)
    {
        $tmp_config = json_decode(file_get_contents($this->_config_file), true);
        $tmp_config[$this->_environment] = $data;
        file_put_contents($this->_config_file, json_encode($tmp_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
