<?php
namespace Pressmind\REST;

error_reporting(1);
ini_set('display_errors', 1);

use Pressmind\MVC\Request;
use Pressmind\MVC\Response;
use Pressmind\MVC\Router;
use Pressmind\Registry;
use \Exception;
/**
 * Class Server
 * @package Pressmind\REST
 * @link http://pmt2core/docs/classes/Pressmind.Rest.Server.html
 */
class Server
{
    /**
     * @var Request
     */
    private $_request;

    /**
     * @var Response
     */
    private $_response;

    /**
     * @var Router
     */
    private $_router;

    /**
     * @var array
     */
    private $_output_methods = ['GET', 'POST'];

    /**
     * @var array
     */
    private $_header_methods = ['OPTIONS', 'HEAD'];

    private $_cache_enabled = false;

    /**
     * Server constructor.
     * @param null $pApiBaseUrl
     */
    public function __construct($pApiBaseUrl = null)
    {
        $this->_request = new Request($pApiBaseUrl);
        $this->_response = new Response();
        $this->_router = new Router();
        $this->_router->addRoute(new Router\Route('search', 'POST', '\\Pressmind\\REST\\Controller', 'Search', 'search'));
        $this->_router->addRoute(new Router\Route('mediaObject/getByRoute', 'POST', '\\Pressmind\\REST\\Controller', 'MediaObject', 'getByRoute'));
        $pieces = (array_map('ucfirst', explode('/', $this->_request->getUri())));
        if(class_exists('\Custom\\REST\\Controller\\' . implode('\\', $pieces))) {
            $this->_router->addRoute(new Router\Route($this->_request->getUri(), 'GET', '\\Custom\\REST\\Controller', implode('\\', $pieces), 'index'));
            $this->_router->addRoute(new Router\Route($this->_request->getUri(), 'POST', '\\Custom\\REST\\Controller', implode('\\', $pieces), 'index'));
        }
        if(class_exists('\Pressmind\\REST\\Controller\\' . implode('\\', $pieces))) {
            $this->_router->addRoute(new Router\Route($this->_request->getUri(), 'GET', '\\Pressmind\\REST\\Controller', implode('\\', $pieces), 'listAll'));
            $this->_router->addRoute(new Router\Route($this->_request->getUri(), 'POST', '\\Pressmind\\REST\\Controller', implode('\\', $pieces), 'listAll'));
        }
        $config = Registry::getInstance()->get('config');
        $this->_cache_enabled = ($config['cache']['enabled'] == true && in_array('REST', $config['cache']['types']) && $this->_request->getParameter($config['cache']['disable_parameter']['key']) != $config['cache']['disable_parameter']['value']);
    }

    /**
     * For now the authentication is disabled by always returning true, might change in feature releases
     * @return bool
     */
    private function _checkAuthentication()
    {
        $config = Registry::getInstance()->get('config');
        if(isset($config['rest']['server']['api_user']) && isset($config['rest']['server']['api_password']) && !empty($config['rest']['server']['api_user']) && !empty($config['rest']['server']['api_password'])) {
            if ($auth = $this->_request->getParsedBasicAuth()) {
                if ($auth[0] == $config['rest']['server']['api_user'] && $auth[1] == $config['rest']['server']['api_password']) {
                    return true;
                }
            } else {
                return false;
            }
        } else {
            return true;
        }
        return false;
    }

    /**
     *
     */
    public function handle() {
        if(!in_array($this->_request->getMethod(), array_merge($this->_output_methods, $this->_header_methods))) {
            $this->_response->setCode(405);
            $this->_response->send();
            die();
        }
        if(in_array($this->_request->getMethod(), $this->_header_methods)) {
            if($this->_request->getMethod() == 'OPTIONS') {
                $this->_response->addHeader('Allow', implode(',', array_merge($this->_output_methods, $this->_header_methods)));
                $this->_response->addHeader('Access-Control-Allow-Origin', '*');
                $this->_response->addHeader('Access-Control-Allow-Methods', implode(',', array_merge($this->_output_methods, $this->_header_methods)));
                $this->_response->addHeader('Access-Control-Allow-Headers', 'Origin, Content-Type, X-Auth-Token, Authorization, Cache-Control, Pragma, Expires');
                $this->_response->addHeader('Access-Control-Max-Age', '60');
            }
            $this->_response->setCode(204);
            $this->_response->send();
            die();
        }
        if($this->_checkAuthentication()) {
            $this->_response->setContentType('application/json');
            $this->_response->addHeader('Access-Control-Allow-Origin', '*');
            $this->_response->addHeader('Access-Control-Allow-Methods', implode(',', array_merge($this->_output_methods, $this->_header_methods)));
            $this->_response->addHeader('Access-Control-Allow-Headers', 'Origin, Content-Type, X-Auth-Token, Authorization, Cache-Control, Pragma, Expires');
            $this->_response->addHeader('Cache-Control', 'no-cache');
            if ($route_match = $this->_router->handle($this->_request)) {
                $classname = $route_match['module'] . '\\' . $route_match['controller'];
                if (class_exists($classname)) {
                    try {
                        $class = new $classname();
                        $method = $route_match['action'];
                        if(method_exists($class, $method)) {
                            $config = Registry::getInstance()->get('config');
                            $parameters = $this->_request->getParameters();
                            $cache_update = ($this->_request->getParameter($config['cache']['update_parameter']['key']) == $config['cache']['update_parameter']['value']);
                            unset($parameters[$config['cache']['update_parameter']['key']]);
                            unset($parameters[$config['cache']['disable_parameter']['key']]);
                            if($this->_cache_enabled) {
                                $cache_adapter = \Pressmind\Cache\Adapter\Factory::create($config['cache']['adapter']['name']);
                                $key = md5($classname . $method . json_encode($parameters));
                                $result = $cache_adapter->get($key);
                                if($result && !$cache_update) {
                                    $return = json_decode($result);
                                } else {
                                    $request =  [
                                        'type' => 'REST',
                                        'classname' => $classname,
                                        'method' => $method,
                                        'parameters' => $parameters
                                    ];
                                    $return = $class->$method($parameters);
                                    $cache_adapter->add($key, json_encode($return), $request);
                                }
                            } else {
                                $return = $class->$method($parameters);
                            }
                            $this->_response->setBody($return);
                        }
                    } catch (Exception $e) {
                        $this->_response->setCode(500);
                        $this->_response->setBody([
                            'error' => true,
                            'msg' => $e->getMessage()
                        ]);
                    }
                } else {
                    $this->_response->setCode(500);
                }
            } else {
                $this->_response->setCode(404);
            }
        } else {
            $this->_response->setCode(403);
        }
        $this->_response->send();
    }

}
