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

        include_once $this->_config_file;
        $return_config['development'] = $config['development'];
        $return_config['production'] = array_merge($config['development'], !empty($config['production']) ? $config['production'] : []);
        $return_config['testing'] = array_merge($config['development'], !empty($config['testing']) ? $config['testing'] : []);
        return $return_config[$this->_environment];
    }

    /**
     * @param array $data
     * @return void
     */
    public function write($data)
    {
        include $this->_config_file;
        /** @var array $config */
        $config[$this->_environment] = $data;
        $config_text = "<?php\n\$config = " . $this->_var_export($config, true) . ';';
        file_put_contents($this->_config_file, $config_text);
    }

    /**
     * @param $expression
     * @param bool $return
     * @return mixed|string|string[]|null
     */
    private function _var_export($expression, $return = false) {
        $export = var_export($expression, true);
        $export = preg_replace("/^([ ]*)(.*)/m", '$1$1$2', $export);
        $array = preg_split("/\r\n|\n|\r/", $export);
        $array = preg_replace(["/\s*array\s\($/", "/\)(,)?$/", "/\s=>\s$/"], [NULL, ']$1', ' => ['], $array);
        $export = join(PHP_EOL, array_filter(["["] + $array));
        if ($return) {
            return $export;
        } else  {
            echo $export;
        }
        return null;
    }
}
