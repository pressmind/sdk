<?php


namespace Pressmind\REST;


use Exception;
use Pressmind\Log\Writer;
use Pressmind\Registry;
use \stdClass;

/**
 * Class Client
 * @package Pressmind\REST
 */
class Client
{
    /**
     * @var string
     */
    private $_api_endpoint = 'https://webcore.pressmind.net/v2-16/rest/';

    /**
     * @var string
     */
    private $_api_key;

    /**
     * @var string
     */
    private $_api_user;

    /**
     * @var string
     */
    private $_api_password;

    /**
     * Client constructor.
     * @param string|null $apiEndpoint
     * @param string|null $apiKey
     * @param string|null $apiUser
     * @param string|null $apiPassword
     */
    public function __construct($apiEndpoint = null, $apiKey = null, $apiUser = null, $apiPassword = null)
    {
        if(is_null($apiEndpoint) && is_null($apiKey) && is_null($apiUser) && is_null($apiPassword)) {
            $config = Registry::getInstance()->get('config');
            if(isset($config['rest']) && is_array($config['rest'])) {
                if(!empty($config['rest']['client']['api_endpoint_overwrite_default'])){
                    $this->_api_endpoint = $config['rest']['client']['api_endpoint_overwrite_default'];
                }
                $this->_api_key = $config['rest']['client']['api_key'];
                $this->_api_user = $config['rest']['client']['api_user'];
                $this->_api_password = $config['rest']['client']['api_password'];
            }
        } else {
            $this->_api_endpoint = $apiEndpoint;
            $this->_api_key = $apiKey;
            $this->_api_user = $apiUser;
            $this->_api_password = $apiPassword;
        }
    }

    /**
     * @param string $controller
     * @param string $action
     * @param array|null $params
     * @return stdClass
     * @throws Exception
     */
    public function sendRequest($controller, $action, $params = null)
    {
        Writer::write('CURL initialized', Writer::OUTPUT_FILE, 'restclient', Writer::TYPE_INFO);
        $ch = curl_init();
        if (is_array($params)) {
            $params['cache'] = 0;
        } else {
            $params = ['cache' => 0];
        }
        $get_params = (is_array($params) && count($params) > 0) ? '?' . http_build_query($params) : '';
        $url = $this->_api_endpoint . $this->_api_key;
        if (!empty($controller)) {
            $url .= '/' . $controller;
        }
        if (!empty($action)) {
            $url .= '/' . $action;
        }
        curl_setopt($ch, CURLOPT_URL, $url . $get_params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->_api_user . ":" . $this->_api_password);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        Writer::write('Sending request to: ' . $this->_api_endpoint . $this->_api_key . '/' . $controller . '/' . $action . $get_params, Writer::OUTPUT_FILE, 'restclient', Writer::TYPE_INFO);
        $response = curl_exec($ch);
        if($response === false) {
            Writer::write('Got no response from: ' . $this->_api_endpoint . $this->_api_key . '/' . $controller . '/' . $action . $get_params, Writer::OUTPUT_FILE, 'restclient', Writer::TYPE_ERROR);
            throw new Exception(curl_error($ch));
        } else {
            Writer::write('Got response from: ' . $this->_api_endpoint . $this->_api_key . '/' . $controller . '/' . $action . $get_params, Writer::OUTPUT_FILE, 'restclient', Writer::TYPE_INFO);
            Writer::write('Parsing response data ...', Writer::OUTPUT_FILE, 'restclient', Writer::TYPE_INFO);
        }
        if (defined('PM_SDK_DEBUG') && PM_SDK_DEBUG) {
            $debug_str = '== DEBUG =='."\n";
            $debug_str .= $this->_api_user . ":" . $this->_api_password."\n";
            $debug_str .= $this->_api_endpoint . $this->_api_key . '/' . $controller . '/' . $action . $get_params."\n";
            $debug_str .= $response."\n";
            $debug_str .= '== DEBUG END =='."\n";
            Writer::write($debug_str, Writer::OUTPUT_SCREEN, 'restclient', Writer::TYPE_INFO);
        }
        $json = json_decode($response);
        if(is_null($json)) {
            switch(json_last_error()) {
                case JSON_ERROR_DEPTH:
                    $error_msg = 'Maximum depth of stack exceeded';
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    $error_msg = 'State mismatch';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $error_msg = 'Unknown control char detected';
                    break;
                case JSON_ERROR_SYNTAX:
                    $error_msg = 'Invalid JSON';
                    break;
                case JSON_ERROR_UTF8:
                    $error_msg = 'UTF8 error: unknown chars detected';
                    break;
                default:
                    $error_msg = 'Unknown error';
                    break;
            }
            Writer::write('Response decoding for: ' . $this->_api_endpoint . $this->_api_key . '/' . $controller . '/' . $action . $get_params . ' failed: ' . $error_msg, Writer::OUTPUT_FILE, 'restclient', Writer::TYPE_ERROR);
            throw new Exception($error_msg);
        } else {
            Writer::write('Response data successfully parsed.', Writer::OUTPUT_FILE, 'restclient', Writer::TYPE_INFO);
        }
        return $json;

    }
}
