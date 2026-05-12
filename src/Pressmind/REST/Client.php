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
     * Webcore API version used for ImportIntegration snapshot/replay.
     * When upgrading: update this constant and re-record fixtures (php tests/bin/record-api-snapshot.php).
     */
    public const WEBCORE_API_VERSION = 'v2-27';

    private const DEFAULT_TIMEOUT = 120;
    private const DEFAULT_CONNECT_TIMEOUT = 30;
    private const DEFAULT_LOW_SPEED_LIMIT = 1;
    private const DEFAULT_LOW_SPEED_TIME = 60;
    private const DEFAULT_MAX_RETRIES = 2;
    private const DEFAULT_RETRY_DELAY_MS = 500;

    /**
     * @return int[]
     */
    private static function retryableCurlErrors(): array
    {
        $errors = [
            CURLE_OPERATION_TIMEDOUT,
            CURLE_COULDNT_CONNECT,
            CURLE_GOT_NOTHING,
            CURLE_RECV_ERROR,
            CURLE_SEND_ERROR,
        ];
        if (defined('CURLE_HTTP2')) {
            $errors[] = CURLE_HTTP2;
        }
        return $errors;
    }

    /**
     * @var string
     */
    private $_api_endpoint = 'https://webcore.pressmind.io/' . self::WEBCORE_API_VERSION . '/rest/';

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
     * @var int
     */
    private $_timeout;

    /**
     * @var int
     */
    private $_connect_timeout;

    /**
     * @var int abort if transfer speed drops below this many bytes/sec …
     */
    private $_low_speed_limit;

    /**
     * @var int … for this many seconds
     */
    private $_low_speed_time;

    /**
     * @var int
     */
    private $_max_retries;

    /**
     * @var int milliseconds between retries
     */
    private $_retry_delay_ms;

    /**
     * Static cURL handles for connection reuse (Keep-Alive)
     * Key: hash of credentials, Value: cURL handle
     * @var array
     */
    private static $_curlHandles = [];

    /**
     * Client constructor.
     *
     * Timeout options (rest.client.timeout, rest.client.connect_timeout,
     * rest.client.low_speed_limit, rest.client.low_speed_time) and retry
     * options (rest.client.max_retries, rest.client.retry_delay_ms) can be
     * set via pm-config.php.
     *
     * @param string|null $apiEndpoint
     * @param string|null $apiKey
     * @param string|null $apiUser
     * @param string|null $apiPassword
     */
    public function __construct($apiEndpoint = null, $apiKey = null, $apiUser = null, $apiPassword = null)
    {
        $clientConfig = [];

        if(is_null($apiEndpoint) && is_null($apiKey) && is_null($apiUser) && is_null($apiPassword)) {
            $config = Registry::getInstance()->get('config');
            if(isset($config['rest']) && is_array($config['rest'])) {
                if(!empty($config['rest']['client']['api_endpoint_overwrite_default'])){
                    trigger_error('The config option rest.client.api_endpoint_overwrite_default is deprecated and will be removed in a future version. 
                    Please use rest.client.api_endpoint instead. Current endpoint is: '.$this->_api_endpoint, E_USER_NOTICE);
                }
                $clientConfig = $config['rest']['client'] ?? [];
                $this->_api_key = $clientConfig['api_key'];
                $this->_api_user = $clientConfig['api_user'];
                $this->_api_password = $clientConfig['api_password'];
            }
        } else {
            $this->_api_endpoint = $apiEndpoint;
            $this->_api_key = $apiKey;
            $this->_api_user = $apiUser;
            $this->_api_password = $apiPassword;
        }

        $this->_timeout = (int)($clientConfig['timeout'] ?? self::DEFAULT_TIMEOUT);
        $this->_connect_timeout = (int)($clientConfig['connect_timeout'] ?? self::DEFAULT_CONNECT_TIMEOUT);
        $this->_low_speed_limit = (int)($clientConfig['low_speed_limit'] ?? self::DEFAULT_LOW_SPEED_LIMIT);
        $this->_low_speed_time = (int)($clientConfig['low_speed_time'] ?? self::DEFAULT_LOW_SPEED_TIME);
        $this->_max_retries = (int)($clientConfig['max_retries'] ?? self::DEFAULT_MAX_RETRIES);
        $this->_retry_delay_ms = (int)($clientConfig['retry_delay_ms'] ?? self::DEFAULT_RETRY_DELAY_MS);
    }

    /**
     * Get or create a reusable cURL handle for the current credentials.
     * Enables HTTP Keep-Alive connection reuse across multiple requests.
     *
     * @param bool $forceNew  destroy existing handle and create a fresh one
     * @return resource|\CurlHandle
     */
    private function _getCurlHandle(bool $forceNew = false)
    {
        $handleKey = md5($this->_api_endpoint . $this->_api_key . $this->_api_user);

        if ($forceNew && isset(self::$_curlHandles[$handleKey])) {
            curl_close(self::$_curlHandles[$handleKey]);
            unset(self::$_curlHandles[$handleKey]);
            Writer::write('CURL handle force-recycled (fresh connection)', Writer::OUTPUT_FILE, 'restclient', Writer::TYPE_INFO);
        }

        if (!isset(self::$_curlHandles[$handleKey]) || self::$_curlHandles[$handleKey] === null) {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->_timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->_connect_timeout);
            curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, $this->_low_speed_limit);
            curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, $this->_low_speed_time);
            curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $this->_api_user . ":" . $this->_api_password);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
            curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 120);
            curl_setopt($ch, CURLOPT_TCP_KEEPINTVL, 60);

            if (defined('CURL_HTTP_VERSION_2_0')) {
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
            }

            self::$_curlHandles[$handleKey] = $ch;
            Writer::write('CURL handle created and cached (connection reuse enabled)', Writer::OUTPUT_FILE, 'restclient', Writer::TYPE_INFO);
        }

        return self::$_curlHandles[$handleKey];
    }

    /**
     * Close all cached cURL handles (call at end of import process if needed)
     */
    public static function closeAllHandles()
    {
        foreach (self::$_curlHandles as $key => $ch) {
            if ($ch !== null) {
                curl_close($ch);
            }
            unset(self::$_curlHandles[$key]);
        }
        self::$_curlHandles = [];
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
        $fullUrl = $url . $get_params;
        $logUrl = $this->_api_endpoint . $this->_api_key . '/' . $controller . '/' . $action . $get_params;

        $lastException = null;
        $maxAttempts = 1 + max(0, $this->_max_retries);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $forceNew = ($attempt > 1);
            $ch = $this->_getCurlHandle($forceNew);

            Writer::write('Sending request to: ' . $logUrl . ($attempt > 1 ? ' (retry ' . ($attempt - 1) . '/' . $this->_max_retries . ')' : ''), Writer::OUTPUT_FILE, 'restclient', Writer::TYPE_INFO);

            $result = $this->executeRequest($ch, $fullUrl);
            $response = $result['body'];
            $status_code = $result['http_code'];

            if ($response === false) {
                $curlErrno = curl_errno($ch);
                $curlError = curl_error($ch);
                Writer::write('CURL error (' . $curlErrno . '): ' . $curlError . ' for: ' . $logUrl, Writer::OUTPUT_FILE, 'restclient', Writer::TYPE_ERROR);

                if ($attempt < $maxAttempts && in_array($curlErrno, self::retryableCurlErrors(), true)) {
                    Writer::write('Retryable error, will retry in ' . $this->_retry_delay_ms . 'ms with fresh connection', Writer::OUTPUT_FILE, 'restclient', Writer::TYPE_INFO);
                    usleep($this->_retry_delay_ms * 1000);
                    $lastException = new Exception('CURL error (' . $curlErrno . '): ' . $curlError);
                    continue;
                }

                throw new Exception('CURL error (' . $curlErrno . '): ' . $curlError);
            }

            Writer::write('Got response from: ' . $logUrl, Writer::OUTPUT_FILE, 'restclient', Writer::TYPE_INFO);
            Writer::write('Parsing response data ...', Writer::OUTPUT_FILE, 'restclient', Writer::TYPE_INFO);

            if (defined('PM_SDK_DEBUG') && PM_SDK_DEBUG) {
                $debug_str = '== DEBUG =='."\n";
                $debug_str .= $this->_api_user . ":" . $this->_api_password."\n";
                $debug_str .= $logUrl."\n";
                if(strlen($response) > 60000) {
                    $debug_str .= 'Response is too long, saving to file...'."\n";
                    file_put_contents('/tmp/restclient_response_' . time() . '.json', json_encode(json_decode($response), JSON_PRETTY_PRINT));
                    $debug_str .= 'Response is too long, view with "less /tmp/restclient_response_' . time() . '.json"'."\n";
                } else{
                    $debug_str .= $response."\n";
                }
                $debug_str .= '== DEBUG END =='."\n";
                Writer::write($debug_str, Writer::OUTPUT_SCREEN, 'restclient', Writer::TYPE_INFO);
            }

            if ($status_code >= 500 && $attempt < $maxAttempts) {
                Writer::write('Server error ' . $status_code . ' for: ' . $logUrl . ', retrying with fresh connection', Writer::OUTPUT_FILE, 'restclient', Writer::TYPE_ERROR);
                usleep($this->_retry_delay_ms * 1000);
                $lastException = new Exception('Response status code is: ' . $status_code . "\nResponse: " . $response);
                continue;
            }

            if($status_code != 200) {
                Writer::write('Response status code for: ' . $logUrl . ' is: ' . $status_code, Writer::OUTPUT_FILE, 'restclient', Writer::TYPE_ERROR);
                throw new Exception('Response status code is: ' . $status_code. "\nResponse: ".$response);
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
                Writer::write('Response decoding for: ' . $logUrl . ' failed: ' . $error_msg, Writer::OUTPUT_FILE, 'restclient', Writer::TYPE_ERROR);
                throw new Exception($error_msg);
            }

            if ($attempt > 1) {
                Writer::write('Request succeeded after ' . ($attempt - 1) . ' retry(ies)', Writer::OUTPUT_FILE, 'restclient', Writer::TYPE_INFO);
            }
            Writer::write('Response data successfully parsed.', Writer::OUTPUT_FILE, 'restclient', Writer::TYPE_INFO);
            return $json;
        }

        throw $lastException ?? new Exception('Request failed after ' . $maxAttempts . ' attempts');
    }

    /**
     * Execute the HTTP request and return raw response and status code.
     * Override in tests to inject responses without real HTTP.
     *
     * @param resource|\CurlHandle $ch
     * @param string $url
     * @return array{body: string|false, http_code: int}
     */
    protected function executeRequest($ch, $url): array
    {
        curl_setopt($ch, CURLOPT_URL, $url);
        $body = curl_exec($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return ['body' => $body, 'http_code' => $http_code];
    }
}
