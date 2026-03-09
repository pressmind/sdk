<?php


namespace Pressmind\Import;


use Exception;
use Pressmind\Registry;
use Pressmind\REST\Client;

class AbstractImport
{
    /**
     * @var \Pressmind\REST\Client|null
     */
    protected $_client = null;

    /**
     * @var array
     */
    protected $_log = [];

    /**
     * @var array
     */
    protected $_errors = [];

    /**
     * @var float
     */
    protected $_start_time;

    /**
     * AbstractImport constructor.
     */
    public function __construct()
    {
        $this->_start_time = microtime(true);
    }

    /**
     * @return array
     */
    public function getLog()
    {
        return $this->_log;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->_errors;
    }

    /**
     * @param $pResponse
     * @return bool
     * @throws Exception
     */
    protected function _checkApiResponse($pResponse)
    {
        if(isset($pResponse->error) && $pResponse->error == true) {
            throw new Exception($pResponse->msg);
        }
        if (is_a($pResponse, 'stdClass') && isset($pResponse->result) && is_array($pResponse->result)) {
            return true;
        }
        if(!isset($pResponse->result) || !is_a($pResponse, 'stdClass')) {
            throw new Exception('API response is not well formatted.');
        }
    }

    /**
     * Returns formatted elapsed time and heap memory usage for logging
     * @return string
     */
    protected function _getElapsedTimeAndHeap()
    {
        $text = number_format(microtime(true) - $this->_start_time, 4) . ' sec | Heap: ';
        $text .= bcdiv(memory_get_usage(), (1000 * 1000), 2) . ' MByte';
        return $text;
    }

    /**
     * Returns the REST client (Registry or new instance). Lazy-init for testability.
     * @return \Pressmind\REST\Client
     */
    protected function getClient(): \Pressmind\REST\Client
    {
        if ($this->_client === null) {
            $this->_client = Registry::getInstance()->get('rest_client') ?? new Client();
        }
        return $this->_client;
    }
}
