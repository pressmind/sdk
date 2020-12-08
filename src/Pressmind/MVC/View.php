<?php


namespace Pressmind\MVC;


use Exception;
use Pressmind\HelperFunctions;

/**
 * Class View
 * @package Pressmind\MVC
 */
class View
{
    private $_data = [];

    private $_view_script;

    public function __construct($pathToViewScript = null)
    {
        $this->setViewScript($pathToViewScript);
    }

    /**
     * @param null $pData
     * @return false|string
     * @throws Exception
     */
    public function render($pData = null) {
        $this->setData($pData);

        $snippet_file = ucfirst(str_replace('APPLICATION_PATH', APPLICATION_PATH, $this->_view_script)) . '.php';
        if(!file_exists($snippet_file)) {
            throw new Exception('Template file ' . $snippet_file . ' does not exist');
        }
        $data = $this->_data;
        ob_start();
        require($snippet_file);
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    public function setData($data, $value = null) {
        if(!is_array($data) && !is_object($data)) {
            $data = [$data => $value];
        }
        $this->_data = $data;
    }

    public function setViewScript($pPathToViewScript) {
        $this->_view_script = $pPathToViewScript;
    }
}
