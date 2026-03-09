<?php

namespace Pressmind\Tests\Unit\MVC;

use PHPUnit\Framework\TestCase;
use Pressmind\MVC\Request;
use Pressmind\MVC\Router;
use Pressmind\MVC\Router\Route;

class RouterTest extends TestCase
{
    private function createMockRequest(string $method, string $uri, array $params = []): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);
        $request->method('getParameters')->willReturn($params);
        return $request;
    }

    public function testAddRouteAndHandleMatchingRoute(): void
    {
        $router = new Router();
        $route = new Route('/users', 'GET');
        $router->addRoute($route);

        $request = $this->createMockRequest('GET', '/users');
        $result = $router->handle($request);

        $this->assertIsArray($result);
    }

    public function testHandleReturnsFalseWhenNoRouteMatches(): void
    {
        $router = new Router();
        $route = new Route('/users', 'GET');
        $router->addRoute($route);

        $request = $this->createMockRequest('GET', '/products');
        $result = $router->handle($request);

        $this->assertFalse($result);
    }

    public function testHandleReturnsFalseOnMethodMismatch(): void
    {
        $router = new Router();
        $route = new Route('/users', 'POST');
        $router->addRoute($route);

        $request = $this->createMockRequest('GET', '/users');
        $result = $router->handle($request);

        $this->assertFalse($result);
    }

    public function testAddRoutesAddsMultipleRoutes(): void
    {
        $router = new Router();
        $router->addRoutes([
            new Route('/users', 'GET'),
            new Route('/products', 'GET'),
        ]);

        $request1 = $this->createMockRequest('GET', '/users');
        $request2 = $this->createMockRequest('GET', '/products');

        $this->assertIsArray($router->handle($request1));
        $this->assertIsArray($router->handle($request2));
    }

    public function testFirstMatchingRouteWins(): void
    {
        $router = new Router();
        $router->addRoute(new Route('/items', 'GET', 'mod1', 'ctrl1', 'act1'));
        $router->addRoute(new Route('/items', 'GET', 'mod2', 'ctrl2', 'act2'));

        $request = $this->createMockRequest('GET', '/items');
        $result = $router->handle($request);

        $this->assertSame('mod1', $result['module']);
        $this->assertSame('ctrl1', $result['controller']);
        $this->assertSame('act1', $result['action']);
    }

    public function testHandleWithNoRoutesTriggersWarning(): void
    {
        $router = new Router();
        $request = $this->createMockRequest('GET', '/anything');

        // _routes is uninitialized (null), so foreach triggers a deprecation/warning.
        // PHPUnit 10 removed expectWarning(), so we catch it via error handler.
        $warningTriggered = false;
        set_error_handler(function () use (&$warningTriggered) {
            $warningTriggered = true;
            return true;
        });
        $result = $router->handle($request);
        restore_error_handler();

        $this->assertTrue($warningTriggered);
        $this->assertFalse($result);
    }

    public function testRouteWithParametersReturnsMatchedParams(): void
    {
        $router = new Router();
        $router->addRoute(new Route('/users/{id}', 'GET'));

        $request = $this->createMockRequest('GET', '/users/42');
        $result = $router->handle($request);

        $this->assertIsArray($result);
        $this->assertSame('42', $result['id']);
    }
}
