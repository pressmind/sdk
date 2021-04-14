<?php


namespace Pressmind\MVC;


use Pressmind\HelperFunctions;
use stdClass;

class Request
{
    /**
     * @var string
     */
    private $_raw_uri;

    /**
     * @var string
     */
    private $_uri;

    /**
     * @var array
     */
    private $_uri_array;

    /**
     * @var array
     */
    private $_headers;

    /**
     * @var string
     */
    private $_method;

    /**
     * @var string|array
     */
    private $_raw_body;

    /**
     * @var array
     */
    private $_body;

    /**
     * @var string
     */
    private $_content_type;

    /**
     * @var array
     */
    private $_parameters = [];

    /**
     * Request constructor.
     * @param string $pBaseUrl
     */
    public function __construct($pBaseUrl = '/')
    {
        $this->_method = $_SERVER['REQUEST_METHOD'];
        $this->_parseRequestHeaders();
        $this->_parseRequestUri($pBaseUrl);
        $this->_parseRequestBody();
    }

    /**
     * @return array|false
     */
    private function _apache_request_headers()
    {
        if (function_exists('apache_request_headers')) {
            return apache_request_headers();
        }
        $arh = [];
        $rx_http = '/\AHTTP_/';
        $additional_headers = ['CONTENT_TYPE'];
        foreach ($_SERVER as $key => $val) {
            if (preg_match($rx_http, $key) || in_array($key, $additional_headers)) {
                $arh_key = preg_replace($rx_http, '', $key);
                $rx_matches = explode('_', $arh_key);
                if (count($rx_matches) > 0 and strlen($arh_key) > 2) {
                    foreach ($rx_matches as $ak_key => $ak_val) $rx_matches[$ak_key] = ucfirst(strtolower($ak_val));
                    $arh_key = implode('-', $rx_matches);
                }
                $arh[$arh_key] = $val;
            }
        }
        return ($arh);
    }

    public function getParsedBasicAuth()
    {
        if(isset($this->_headers['Authorization']) && !empty($this->_headers['Authorization'])) {
            if(strpos('Basic ') !== false) {
                return explode(':', base64_decode(str_replace('Basic ', '', $this->_headers['Authorization'])));
            }
        }
        return false;
    }

    /**
     *
     */
    private function _parseRequestHeaders()
    {
        $this->_headers = $this->_apache_request_headers();
        $this->_content_type = isset($this->_headers['content-type']) ? $this->_headers['content-type'] : 'application/octet-stream';
    }

    /**
     * @param null|string $pBaseUrl
     */
    private function _parseRequestUri($pBaseUrl = null)
    {
        $this->_raw_uri = $_SERVER['REQUEST_URI'];
        if (strpos($this->_raw_uri, '?') !== false) {
            $request_array = explode('?', $this->_raw_uri);
            $this->_raw_uri = $request_array[0];
            if(!empty($request_array[1])) {
                parse_str($request_array[1], $this->_parameters);
            }
        }

        if (!empty($pBaseUrl)) {
            $pos = strpos($this->_raw_uri, $pBaseUrl);
            if ($pos !== false) {
                $this->_raw_uri = trim(substr_replace($this->_raw_uri, '', $pos, strlen($pBaseUrl)), '/');
            }
        }

        $this->_uri_array = explode('/', $this->_raw_uri);
        $this->_uri = $this->_raw_uri;
        //$this->_checkAndParseBaseParameters();

        if (count($this->_uri_array) > 3) {
            $this->_parameters = array_merge($this->_parameters, $this->_parseParameters(array_slice($this->_uri_array, 3)));
            $this->_uri = implode('/', array_slice($this->_uri_array, 3));
        }
    }

    /**
     *
     */
    private function _checkAndParseBaseParameters()
    {
        if (empty($this->_uri_array[0])) {
            $this->_uri_array[0] = 'standard';
        }
        $this->_parameters['module'] = $this->_uri_array[0];
        $this->_parameters['controller'] = $this->_uri_array[1];
        $this->_parameters['action'] = $this->_uri_array[2];

        if (!is_dir(HelperFunctions::buildPathString([APPLICATION_PATH, ucfirst($this->_parameters['module'])]))) {
            if ($this->_uri_array[0] != 'standard') {
                array_splice($this->_uri_array, 0, 0, 'standard');
                $this->_parameters['module'] = 'standard';
            }
        } else {
            $this->_parameters['module'] = $this->_uri_array[0];
        }
        if (!class_exists('\\Application\\' . ucfirst($this->_parameters['module']) . '\\Controller\\' . ucfirst($this->_parameters['controller']))) {
            if ($this->_uri_array[1] != 'index') {
                array_splice($this->_uri_array, 1, 0, 'index');
                $this->_parameters['controller'] = 'index';
            }
        } else {
            $this->_parameters['controller'] = $this->_uri_array[1];
        }
        if (!method_exists('\\Application\\' . ucfirst($this->_parameters['module']) . '\\Controller\\' . ucfirst($this->_parameters['controller']), $this->_parameters['action'])) {
            if ($this->_uri_array[2] != 'index') {
                array_splice($this->_uri_array, 2, 0, 'index');
                $this->_parameters['action'] = 'index';
            }
        } else {
            $this->_parameters['action'] = $this->_uri_array[2];
        }
    }

    /**
     * @param $pArray
     * @return array|false
     */
    private function _parseParameters($pArray)
    {
        $keys = [];
        $values = [];
        foreach ($pArray as $index => $val) {
            if ($index % 2 == 0) {
                $keys[] = $val;
            } else {
                if (empty($val)) $val = null;
                $values[] = $val;
            }
        }
        if (count($pArray) % 2 == 1) $values[] = null;
        return array_combine($keys, $values);
    }

    /**
     *
     */
    private function _parseRequestBody()
    {
        if ($this->_method == 'POST' || $this->_method == 'PUT') {
            $content_type = explode(';', $this->_headers['Content-Type'])[0];
            $body = null;
            switch ($content_type) {
                case 'application/x-www-form-urlencoded':
                case 'multipart/form-data':
                    $this->_raw_body = $_POST;
                    $this->_body = $_POST;
                    break;
                case 'application/json':
                    $this->_raw_body = file_get_contents('php://input');
                    $this->_body = json_decode($this->_raw_body, true);
                    break;
                default:
                    $this->_raw_body = file_get_contents('php://input');
                    $this->_body = json_decode($this->_raw_body, true);
            }
            $this->_parameters = array_merge($this->_parameters, $this->_body);
        }
    }

    /**
     * @return array
     */
    public function getBody()
    {
        return $this->_body;
    }

    /**
     * @return string
     */
    public function getContentType()
    {
        return $this->_content_type;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->_headers;
    }

    /**
     * @param $name
     * @return string
     */
    public function getHeader($name) {
        return isset($this->_headers[$name]) ? $this->_headers[$name] : null;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->_method;
    }

    /**
     * @return bool
     */
    public function isPost()
    {
        return strtolower($this->getMethod()) == 'post';
    }

    /**
     * @return array
     */
    public function getPostValues()
    {
        return $this->_body;
    }

    /**
     * @return bool
     */
    public function isGet()
    {
        return strtolower($this->getMethod()) == 'get';
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->_parameters;
    }

    /**
     * @param $key
     * @return mixed|null
     */
    public function getParameter($key)
    {
        return isset($this->_parameters[$key]) ? $this->_parameters[$key] : null;
    }

    public function addParameter($key, $value) {
        $this->_parameters[$key] = $value;
    }

    /**
     * @return string
     */
    public function getRawBody()
    {
        return $this->_raw_body;
    }

    /**
     * @return string
     */
    public function getRawUri()
    {
        return $this->_raw_uri;
    }

    /**
     * @return string
     */
    public function getUri()
    {
        return $this->_uri;
    }

}
