<?php


namespace Pressmind\MVC\Router;


use Pressmind\HelperFunctions;
use Pressmind\MVC\Request;

class Route
{
    private $_module;
    private $_controller;
    private $_action;
    public $method;
    public $route;
    private $_params;
    private $_id;

    /*public function __construct($type = null, $method = null, $route = null)
    {
        $this->route = is_null($route) ? null: $route;
        $this->type = is_null($type) ? null: $type;
        $this->method = is_null($method) ? null: $method;
        $this->parse($route);
    }*/

    public function __construct($pRoute, $pMethod, $pModule = null, $pController = null, $pAction = null)
    {
        $this->_action = is_null($pAction) ? 'index' : $pAction;
        $this->_controller = is_null($pController) ? 'index' : $pController;
        $this->_module = is_null($pModule) ? 'standard' : $pModule;
        $this->method = $pMethod;
        $this->parse($pRoute);
    }

    private function parse($route) {
        // Convert the route to a regular expression: escape forward slashes
        $route = preg_replace('/\//', '\\/', $route);
        // Convert variables e.g. {controller}
        $route = preg_replace('/\{([a-z0-9\-]+)\}/', '(?P<\1>[a-z-0-9\-]+)', $route);
        // Convert variables with custom regular expressions e.g. {id:\d+}
        $route = preg_replace('/\{([a-z]+):([^\}]+)\}/', '(?P<\1>\2)', $route);
        // Add start and end delimiters, and case insensitive flag
        $route = '/^' . $route . '$/i';
        $this->route = $route;
        $this->_id = md5($route);
    }


    private function _matchMethodModuleControllerAction($pRequestParams)
    {
        if($this->_module == $pRequestParams['module'] && $this->_controller == $pRequestParams['controller'] && $this->_action == $pRequestParams['action']) {
            return true;
        }
        return false;
    }

    /**
     * @param Request $pRequest
     * @return array|bool
     */
    public function match($pRequest) {
        $params = [
            'module' => $this->_module,
            'action' => $this->_action,
            'controller' => $this->_controller
        ];
        $params = array_merge($params, $pRequest->getParameters());
        $request_uri = $pRequest->getUri();
        $request_method = $pRequest->getMethod();
        $request_uri = rtrim($request_uri, '/');
        //return $params;
        if($request_method == $this->method && preg_match($this->route, $request_uri, $matches)) {
            foreach ($matches as $key => $match) {
                if (is_string($key)) {
                    $params[$key] = $match;
                    $pRequest->addParameter($key, $match);
                }
            }
            $this->_params = $params;
            return $params;
        } else {
            return false;
        }
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * @param boolean $stripRouterParams
     * @return array
     */
    public function getParams($stripRouterParams = false)
    {
        if(true == $stripRouterParams) {
            $params = $this->_params;
            unset($params['module']);
            unset($params['controller']);
            unset($params['action']);
            return $params;
        }
        return $this->_params;
    }
}
