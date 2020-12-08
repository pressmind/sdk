<?php


namespace Pressmind\MVC;


use Pressmind\HelperFunctions;
use Pressmind\Registry;

class Dispatcher
{
    /**
     * @var Router
     */
    private $_router;

    /**
     * @var Request
     */
    private $_request;

    /**
     * @var Response
     */
    private $_response;

    private static $_instance = null;

    private $_layout_enabled = true;

    public function __construct()
    {
        $response = new Response();
        $response->setContentType('text/html');
        $this->_response = $response;
    }

    public static function getInstance()
    {
        if(is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * @param Request $request
     */
    public function setRequest($request)
    {
        $this->_request = $request;
    }

    /**
     * @param Response $response
     */
    public function setResponse($response)
    {
        $this->_response = $response;
    }

    /**
     * @param Router $router
     */
    public function setRouter($router)
    {
        $this->_router = $router;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->_request;
    }

    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->_response;
    }

    /**
     * @return Router
     */
    public function getRouter()
    {
        return $this->_router;
    }

    public function disableLayout()
    {
        $this->_layout_enabled = false;
    }

    public function dispatch()
    {
        $config = Registry::getInstance()->get('config');
        $result = $this->_router->handle($this->_request);
        $this->_response->setContentType('application/json');
        $this->_response->setBody(json_encode($this->getRequest()->getParameters()));
        $this->_response->setCode(200);
        $this->_response->send();
    }
}
