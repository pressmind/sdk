<?php


namespace Pressmind\REST\Controller;


use Exception;
use Pressmind\AbstractController;

class Error extends AbstractController
{
    /**
     * @param string $message
     * @param Exception $exception
     * @return array
     */
    public function index()
    {
        return [
            'error' => true,
            'message' => $this->parameters['message'],
            'trace' => $this->parameters['exception']->getTrace()
        ];
    }
}
