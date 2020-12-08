<?php


namespace Pressmind\Import;


use Exception;

class AbstractImport
{
    /**
     * @var array
     */
    protected $_log;

    /**
     * @var array
     */
    protected $_errors;

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
        $error_msg = '';
        if (is_a($pResponse, 'stdClass') && isset($pResponse->result) && is_array($pResponse->result) && isset($pResponse->error) && $pResponse->error == false) {
            return true;
        }
        if(!isset($pResponse->result) || !isset($pResponse->error) || !isset($pResponse->msg) || !is_a($pResponse, 'stdClass')) {
            $error_msg = 'API response is not well formatted.';
        }
        if($pResponse->error == true) {
            $error_msg = $pResponse->msg;
        }
        throw new Exception($error_msg);
    }
}
