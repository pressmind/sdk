<?php


namespace Pressmind\Import;


use Exception;

class AbstractImport
{
    /**
     * @var array
     */
    protected $_log = [];

    /**
     * @var array
     */
    protected $_errors = [];

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
}
