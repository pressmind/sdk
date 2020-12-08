<?php

namespace Pressmind\MVC;
use Pressmind\HelperFunctions;

/**
 * Class AbstractController
 * @package Pressmind
 */
abstract class AbstractController
{

    /**
     * @var array
     */
    private $_headerScripts = [];

    /**
     * @var array
     */
    private $_headerScriptIncludes = [];

    /**
     * @var array
     */
    private $_footerScriptIncludes = [];

    /**
     * @var array
     */
    private $_cssStyleIncludes = [];

    /**
     * @var array
     */
    private $_httpHeaders = [];

    /**
     * @var View
     */
    protected $view;

    /**
     * @var array
     */
    public $parameters;

    /**
     * AbstractController constructor.
     * @param array $parameters
     */
    public function __construct($parameters)
    {
        $this->parameters = $parameters;
        $this->view = new View();
        $script_path = HelperFunctions::buildPathString(
            [
                ucfirst($this->getParameter('module')),
                'View',
                ucfirst($this->getParameter('controller')),
                ucfirst($this->getParameter('action')),
            ]
        );
        $this->view->setViewScript($script_path);
    }

    public function init()
    {

    }

    /**
     * @param $key
     * @param string $default_value
     * @return mixed|null
     */
    public function getParameter($key, $default_value = null)
    {
        return (isset($this->parameters[$key]) && !empty($this->parameters[$key])) ? $this->parameters[$key] : $default_value;
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function setParameter($key, $value)
    {
        $this->parameters[$key] = $value;
    }

    public function addHeaderScript($scriptContent)
    {
        $id = md5($scriptContent);
        if(!isset($this->_headerScripts[$id])) {
            $this->_headerScripts[$id] = $scriptContent;
        }
    }

    /**
     * @param string $url
     * @param array $attributes
     */
    public function addHeaderScriptInclude($url, $attributes = [])
    {
        $id = md5($url);
        if(!isset($this->_headerScriptIncludes[$id])) {
            $this->_headerScriptIncludes[$id] = ['url' => $url, 'attributes' => $attributes];
        }
    }

    /**
     * @param string $url
     * @param array $attributes
     */
    public function addFooterScriptInclude($url, $attributes = [])
    {
        $id = md5($url);
        if(!isset($this->_footerScriptIncludes[$id])) {
            $this->_footerScriptIncludes[$id] = ['url' => $url, 'attributes' => $attributes];
        }
    }

    /**
     * @param string $url
     * @param array $attributes
     */
    public function addCssStyleInclude($url, $attributes = [])
    {
        $id = md5($url);
        if(!isset($this->_cssStyleIncludes[$id])) {
            $this->_cssStyleIncludes[$id] = ['url' => $url, 'attributes' => $attributes];
        }
    }

    /**
     * @param string $key
     * @param string $value
     */
    public function addHttpHeader($key, $value)
    {
        $this->_httpHeaders[$key] = $value;
    }


    public function renderHeaderScripts() {
        $html = [];
        foreach ($this->_headerScripts as $headerScript) {
            $html[] = '<script>' . $headerScript . '</script>';
        }
        return implode($html);
    }

    /**
     * @param array $includes
     * @return string
     */
    private function _renderScriptIncludes($includes)
    {
        $html = [];
        foreach ($includes as $scriptInclude) {
            $attributes = [];
            foreach ($scriptInclude['attributes'] as $attribute_name => $attribute_value) {
                $attributes[] = $attribute_name . '="' . $attribute_value . '"';
            }
            $html[] = '<script src="' . $scriptInclude['url'] . '" ' . implode(' ', $attributes) . '></script>';
        }
        return implode($html);
    }

    /**
     * @return string
     */
    public function renderCssStyleIncludes()
    {
        $html = [];
        foreach ($this->_cssStyleIncludes as $cssStyleInclude) {
            $attributes = [];
            foreach ($cssStyleInclude['attributes'] as $attribute_name => $attribute_value) {
                $attributes[] = $attribute_name . '="' . $attribute_value . '"';
            }
            $html[] = '<link rel="stylesheet" href="' . $cssStyleInclude['url'] . '" ' . implode(' ', $attributes) . '>';
        }
        return implode($html);
    }

    /**
     * @return string
     */
    public function renderHeaderScriptIncludes()
    {
        return $this->_renderScriptIncludes($this->_headerScriptIncludes);
    }

    /**
     * @return string
     */
    public function renderFooterScriptIncludes()
    {
        return $this->_renderScriptIncludes($this->_footerScriptIncludes);
    }
}
