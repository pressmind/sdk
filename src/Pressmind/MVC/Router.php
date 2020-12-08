<?php


namespace Pressmind\MVC;


use Pressmind\MVC\Router\Route;

class Router
{
    /**
     * @var Route[]
     */
    private $_routes;

    public function __construct()
    {
    }

    public function addRoutes($pRoutes)
    {
        foreach ($pRoutes as $route) {
            $this->addRoute($route);
        }
    }

    /**
     * @param Route $pRoute
     */
    public function addRoute($pRoute)
    {
        $this->_routes[] = $pRoute;
    }

    /**
     * @param Request $pRequest
     * @return array|false
     */
    public function handle($pRequest)
    {
        $params = [];
        $matched = false;
        foreach ($this->_routes as $route) {
            if ($params = $route->match($pRequest)) {
                $matched = true;
                break;
            }
        }
        return ($matched == true ? $params : false);
    }
}
