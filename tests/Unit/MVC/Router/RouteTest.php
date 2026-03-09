<?php

namespace Pressmind\Tests\Unit\MVC\Router;

use PHPUnit\Framework\TestCase;
use Pressmind\MVC\Request;
use Pressmind\MVC\Router\Route;

class RouteTest extends TestCase
{
    private function createMockRequest(string $method, string $uri, array $params = []): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);
        $request->method('getParameters')->willReturn($params);
        return $request;
    }

    public function testConstructorDefaultValues(): void
    {
        $route = new Route('/test', 'GET');
        $this->assertSame('GET', $route->method);
        $this->assertNotEmpty($route->getId());
    }

    public function testConstructorWithAllParameters(): void
    {
        $route = new Route('/test', 'POST', 'admin', 'users', 'create');
        $this->assertSame('POST', $route->method);

        $request = $this->createMockRequest('POST', '/test');
        $result = $route->match($request);

        $this->assertSame('admin', $result['module']);
        $this->assertSame('users', $result['controller']);
        $this->assertSame('create', $result['action']);
    }

    public function testDefaultModuleControllerAction(): void
    {
        $route = new Route('/test', 'GET');

        $request = $this->createMockRequest('GET', '/test');
        $result = $route->match($request);

        $this->assertSame('standard', $result['module']);
        $this->assertSame('index', $result['controller']);
        $this->assertSame('index', $result['action']);
    }

    public function testMatchReturnsFalseOnMethodMismatch(): void
    {
        $route = new Route('/test', 'GET');
        $request = $this->createMockRequest('POST', '/test');
        $this->assertFalse($route->match($request));
    }

    public function testMatchReturnsFalseOnUriMismatch(): void
    {
        $route = new Route('/users', 'GET');
        $request = $this->createMockRequest('GET', '/products');
        $this->assertFalse($route->match($request));
    }

    public function testMatchExtractsNamedParameters(): void
    {
        $route = new Route('/users/{id}', 'GET');
        $request = $this->createMockRequest('GET', '/users/123');
        $result = $route->match($request);

        $this->assertIsArray($result);
        $this->assertSame('123', $result['id']);
    }

    public function testMatchExtractsMultipleNamedParameters(): void
    {
        $route = new Route('/users/{userid}/posts/{postid}', 'GET');
        $request = $this->createMockRequest('GET', '/users/5/posts/99');
        $result = $route->match($request);

        $this->assertIsArray($result);
        $this->assertSame('5', $result['userid']);
        $this->assertSame('99', $result['postid']);
    }

    public function testMatchWithCustomRegex(): void
    {
        $route = new Route('/items/{id:\d+}', 'GET');

        $requestValid = $this->createMockRequest('GET', '/items/42');
        $this->assertIsArray($route->match($requestValid));
        $this->assertSame('42', $route->match($requestValid)['id']);

        $requestInvalid = $this->createMockRequest('GET', '/items/abc');
        $this->assertFalse($route->match($requestInvalid));
    }

    public function testMatchIsCaseInsensitive(): void
    {
        $route = new Route('/Users', 'GET');
        $request = $this->createMockRequest('GET', '/users');
        $this->assertIsArray($route->match($request));
    }

    public function testMatchMergesRequestParameters(): void
    {
        $route = new Route('/test', 'GET');
        $request = $this->createMockRequest('GET', '/test', ['foo' => 'bar']);
        $result = $route->match($request);

        $this->assertSame('bar', $result['foo']);
    }

    public function testGetIdReturnsMd5Hash(): void
    {
        $route = new Route('/test', 'GET');
        $id = $route->getId();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $id);
    }

    public function testDifferentRoutesHaveDifferentIds(): void
    {
        $route1 = new Route('/route-one', 'GET');
        $route2 = new Route('/route-two', 'GET');
        $this->assertNotSame($route1->getId(), $route2->getId());
    }

    public function testSameRoutePatternProducesSameId(): void
    {
        $route1 = new Route('/identical', 'GET');
        $route2 = new Route('/identical', 'GET');
        $this->assertSame($route1->getId(), $route2->getId());
    }

    public function testGetParamsAfterMatch(): void
    {
        $route = new Route('/api/data', 'GET', 'api', 'data', 'list');
        $request = $this->createMockRequest('GET', '/api/data', ['page' => '2']);
        $route->match($request);

        $params = $route->getParams();
        $this->assertSame('api', $params['module']);
        $this->assertSame('data', $params['controller']);
        $this->assertSame('list', $params['action']);
        $this->assertSame('2', $params['page']);
    }

    public function testGetParamsStripsRouterParams(): void
    {
        $route = new Route('/api/data', 'GET', 'api', 'data', 'list');
        $request = $this->createMockRequest('GET', '/api/data', ['page' => '2']);
        $route->match($request);

        $stripped = $route->getParams(true);
        $this->assertArrayNotHasKey('module', $stripped);
        $this->assertArrayNotHasKey('controller', $stripped);
        $this->assertArrayNotHasKey('action', $stripped);
        $this->assertSame('2', $stripped['page']);
    }

    public function testMatchTrimsTrailingSlash(): void
    {
        $route = new Route('/test', 'GET');
        $request = $this->createMockRequest('GET', '/test/');
        $this->assertIsArray($route->match($request));
    }

    public function testMatchWithNestedPath(): void
    {
        $route = new Route('/api/v1/resources', 'GET');
        $request = $this->createMockRequest('GET', '/api/v1/resources');
        $this->assertIsArray($route->match($request));
    }
}
