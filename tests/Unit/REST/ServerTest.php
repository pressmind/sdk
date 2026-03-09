<?php

namespace Pressmind\Tests\Unit\REST;

use Pressmind\MVC\Request;
use Pressmind\MVC\Response;
use Pressmind\MVC\Router;
use Pressmind\Registry;
use Pressmind\REST\Server;
use Pressmind\Tests\Unit\AbstractTestCase;

class ServerTest extends AbstractTestCase
{
    private array $originalServer;
    private array $originalGet;

    protected $defaultConfig = [
        'cache' => [
            'enabled' => false,
            'types' => [],
            'disable_parameter' => ['key' => 'nocache', 'value' => '1'],
            'update_parameter' => ['key' => 'updatecache', 'value' => '1'],
            'adapter' => ['name' => 'Redis'],
        ],
        'database' => ['dbname' => 'test'],
        'logging' => ['enable_advanced_object_log' => false],
        'rest' => ['server' => []],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalServer = $_SERVER;
        $this->originalGet = $_GET;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_GET = $this->originalGet;
        parent::tearDown();
    }

    /**
     * Build a Request from simulated $_SERVER globals.
     * Set auth headers on $_SERVER BEFORE calling this method.
     */
    private function buildRequest(string $method, string $uri): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        return new Request('/');
    }

    private function buildServer(Request $request, ?Router $router = null): Server
    {
        $router = $router ?? new Router();
        return new Server(null, $request, new Response(), $router);
    }

    // --- HTTP Method tests ---

    public function testDeleteMethodReturns405(): void
    {
        $request = $this->buildRequest('DELETE', '/foo');
        $response = $this->buildServer($request)->run();
        $this->assertSame(405, $response->getCode());
    }

    public function testPatchMethodReturns405(): void
    {
        $request = $this->buildRequest('PATCH', '/foo');
        $response = $this->buildServer($request)->run();
        $this->assertSame(405, $response->getCode());
    }

    public function testPutMethodReturns405(): void
    {
        $request = $this->buildRequest('PUT', '/foo');
        $response = $this->buildServer($request)->run();
        $this->assertSame(405, $response->getCode());
    }

    // --- OPTIONS / HEAD ---

    public function testOptionsReturns204WithCorsHeaders(): void
    {
        $request = $this->buildRequest('OPTIONS', '/anything');
        $response = $this->buildServer($request)->run();
        $this->assertSame(204, $response->getCode());
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('Access-Control-Allow-Origin', $headers);
        $this->assertSame('*', $headers['Access-Control-Allow-Origin']);
        $this->assertArrayHasKey('Access-Control-Allow-Methods', $headers);
        $this->assertArrayHasKey('Access-Control-Allow-Headers', $headers);
        $this->assertArrayHasKey('Access-Control-Max-Age', $headers);
        $this->assertArrayHasKey('Allow', $headers);
    }

    public function testHeadReturns204(): void
    {
        $request = $this->buildRequest('HEAD', '/anything');
        $response = $this->buildServer($request)->run();
        $this->assertSame(204, $response->getCode());
    }

    // --- 404 no matching route ---

    public function testGetWithNoMatchingRouteReturns404(): void
    {
        $request = $this->buildRequest('GET', '/nonexistent');
        $response = $this->buildServer($request)->run();
        $this->assertSame(404, $response->getCode());
    }

    public function testPostWithNoMatchingRouteReturns404(): void
    {
        $request = $this->buildRequest('POST', '/nonexistent');
        $response = $this->buildServer($request)->run();
        $this->assertSame(404, $response->getCode());
    }

    // --- Authentication: no auth configured -> 200 (pass through) ---

    public function testNoAuthConfiguredAllowsRequest(): void
    {
        $request = $this->buildRequest('GET', '/test');
        $router = new Router();
        $router->addRoute(new Router\Route('test', 'GET', __NAMESPACE__, 'StubController', 'ok'));
        $response = $this->buildServer($request, $router)->run();
        $this->assertSame(200, $response->getCode());
        $this->assertSame(['ok' => true], $response->getBody());
    }

    // --- Authentication: API key ---

    public function testApiKeyAuthSucceedsWithCorrectKey(): void
    {
        $config = $this->createMockConfig([
            'rest' => ['server' => ['api_key' => 'secret123']],
        ]);
        Registry::getInstance()->add('config', $config);

        $_SERVER['HTTP_X_API_KEY'] = 'secret123';
        $request = $this->buildRequest('GET', '/test');
        $router = new Router();
        $router->addRoute(new Router\Route('test', 'GET', __NAMESPACE__, 'StubController', 'ok'));
        $response = $this->buildServer($request, $router)->run();
        $this->assertSame(200, $response->getCode());
    }

    public function testApiKeyOnlyWithWrongKeyFallsThroughWhenNoBasicAuthConfigured(): void
    {
        $config = $this->createMockConfig([
            'rest' => ['server' => ['api_key' => 'secret123']],
        ]);
        Registry::getInstance()->add('config', $config);

        $_SERVER['HTTP_X_API_KEY'] = 'wrong';
        $request = $this->buildRequest('GET', '/test');
        $router = new Router();
        $router->addRoute(new Router\Route('test', 'GET', __NAMESPACE__, 'StubController', 'ok'));
        $response = $this->buildServer($request, $router)->run();
        // Server auth falls through to "return true" when API key does not match
        // and no basic auth is configured (open-access fallback).
        $this->assertSame(200, $response->getCode());
    }

    public function testApiKeyPlusBasicAuthConfiguredRejects403WhenBothWrong(): void
    {
        $config = $this->createMockConfig([
            'rest' => ['server' => [
                'api_key' => 'secret123',
                'api_user' => 'admin',
                'api_password' => 'pass',
            ]],
        ]);
        Registry::getInstance()->add('config', $config);

        $_SERVER['HTTP_X_API_KEY'] = 'wrong';
        $request = $this->buildRequest('GET', '/test');
        $router = new Router();
        $router->addRoute(new Router\Route('test', 'GET', __NAMESPACE__, 'StubController', 'ok'));
        $response = $this->buildServer($request, $router)->run();
        $this->assertSame(403, $response->getCode());
    }

    public function testApiKeyPlusBasicAuthConfiguredRejects403WhenNoCredentials(): void
    {
        $config = $this->createMockConfig([
            'rest' => ['server' => [
                'api_key' => 'secret123',
                'api_user' => 'admin',
                'api_password' => 'pass',
            ]],
        ]);
        Registry::getInstance()->add('config', $config);

        unset($_SERVER['HTTP_X_API_KEY']);
        $_GET = [];
        $request = $this->buildRequest('GET', '/test');
        $router = new Router();
        $router->addRoute(new Router\Route('test', 'GET', __NAMESPACE__, 'StubController', 'ok'));
        $response = $this->buildServer($request, $router)->run();
        $this->assertSame(403, $response->getCode());
    }

    // --- Authentication: Basic Auth ---

    public function testBasicAuthSucceeds(): void
    {
        $config = $this->createMockConfig([
            'rest' => ['server' => [
                'api_user' => 'admin',
                'api_password' => 'pass',
            ]],
        ]);
        Registry::getInstance()->add('config', $config);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:pass');
        $request = $this->buildRequest('GET', '/test');
        $router = new Router();
        $router->addRoute(new Router\Route('test', 'GET', __NAMESPACE__, 'StubController', 'ok'));
        $response = $this->buildServer($request, $router)->run();
        $this->assertSame(200, $response->getCode());
    }

    public function testBasicAuthFailsWithWrongPassword(): void
    {
        $config = $this->createMockConfig([
            'rest' => ['server' => [
                'api_user' => 'admin',
                'api_password' => 'pass',
            ]],
        ]);
        Registry::getInstance()->add('config', $config);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:wrong');
        $request = $this->buildRequest('GET', '/test');
        $router = new Router();
        $router->addRoute(new Router\Route('test', 'GET', __NAMESPACE__, 'StubController', 'ok'));
        $response = $this->buildServer($request, $router)->run();
        $this->assertSame(403, $response->getCode());
    }

    public function testBasicAuthRejects403WhenNoCredentials(): void
    {
        $config = $this->createMockConfig([
            'rest' => ['server' => [
                'api_user' => 'admin',
                'api_password' => 'pass',
            ]],
        ]);
        Registry::getInstance()->add('config', $config);

        unset($_SERVER['HTTP_AUTHORIZATION']);
        $request = $this->buildRequest('GET', '/test');
        $router = new Router();
        $router->addRoute(new Router\Route('test', 'GET', __NAMESPACE__, 'StubController', 'ok'));
        $response = $this->buildServer($request, $router)->run();
        $this->assertSame(403, $response->getCode());
    }

    public function testBasicAuthWithPasswordContainingColon(): void
    {
        $config = $this->createMockConfig([
            'rest' => ['server' => [
                'api_user' => 'admin',
                'api_password' => 'pass:word:complex',
            ]],
        ]);
        Registry::getInstance()->add('config', $config);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:pass:word:complex');
        $request = $this->buildRequest('GET', '/test');
        $router = new Router();
        $router->addRoute(new Router\Route('test', 'GET', __NAMESPACE__, 'StubController', 'ok'));
        $response = $this->buildServer($request, $router)->run();
        $this->assertSame(200, $response->getCode());
    }

    // --- Controller dispatch ---

    public function testControllerActionCalledAndBodySet(): void
    {
        $request = $this->buildRequest('GET', '/test');
        $router = new Router();
        $router->addRoute(new Router\Route('test', 'GET', __NAMESPACE__, 'StubController', 'ok'));
        $response = $this->buildServer($request, $router)->run();
        $this->assertSame(200, $response->getCode());
        $this->assertSame(['ok' => true], $response->getBody());
    }

    public function testControllerExceptionReturns500(): void
    {
        $request = $this->buildRequest('GET', '/test');
        $router = new Router();
        $router->addRoute(new Router\Route('test', 'GET', __NAMESPACE__, 'StubController', 'fail'));
        $response = $this->buildServer($request, $router)->run();
        $this->assertSame(500, $response->getCode());
        $body = $response->getBody();
        $this->assertTrue($body['error']);
        $this->assertSame('test error', $body['msg']);
    }

    public function testControllerRedirectSets302(): void
    {
        $request = $this->buildRequest('GET', '/test');
        $router = new Router();
        $router->addRoute(new Router\Route('test', 'GET', __NAMESPACE__, 'StubController', 'redirect'));
        $response = $this->buildServer($request, $router)->run();
        $this->assertSame(302, $response->getCode());
        $headers = $response->getHeaders();
        $this->assertSame('https://example.com', $headers['Location']);
    }

    public function testNonexistentControllerReturns500(): void
    {
        $request = $this->buildRequest('GET', '/test');
        $router = new Router();
        $router->addRoute(new Router\Route('test', 'GET', 'Nonexistent\\Namespace', 'Controller', 'action'));
        $response = $this->buildServer($request, $router)->run();
        $this->assertSame(500, $response->getCode());
        $body = $response->getBody();
        $this->assertTrue($body['error']);
        $this->assertStringContainsString('does not exist', $body['msg']);
    }

    public function testNonexistentActionReturns500(): void
    {
        $request = $this->buildRequest('GET', '/test');
        $router = new Router();
        $router->addRoute(new Router\Route('test', 'GET', __NAMESPACE__, 'StubController', 'nonexistent'));
        $response = $this->buildServer($request, $router)->run();
        $this->assertSame(500, $response->getCode());
        $body = $response->getBody();
        $this->assertTrue($body['error']);
        $this->assertStringContainsString('does not exist', $body['msg']);
    }

    // --- CORS headers on success ---

    public function testCorsHeadersSetOnSuccessfulRequest(): void
    {
        $request = $this->buildRequest('GET', '/test');
        $router = new Router();
        $router->addRoute(new Router\Route('test', 'GET', __NAMESPACE__, 'StubController', 'ok'));
        $response = $this->buildServer($request, $router)->run();
        $headers = $response->getHeaders();
        $this->assertSame('*', $headers['Access-Control-Allow-Origin']);
        $this->assertStringContainsString('GET', $headers['Access-Control-Allow-Methods']);
        $this->assertStringContainsString('POST', $headers['Access-Control-Allow-Methods']);
        $this->assertSame('no-cache', $headers['Cache-Control']);
    }

    public function testContentTypeSetToJson(): void
    {
        $request = $this->buildRequest('GET', '/test');
        $router = new Router();
        $router->addRoute(new Router\Route('test', 'GET', __NAMESPACE__, 'StubController', 'ok'));
        $response = $this->buildServer($request, $router)->run();
        $this->assertSame('application/json', $response->getContentType());
    }

    // --- directCall ---

    public function testDirectCallExecutesControllerAction(): void
    {
        $request = $this->buildRequest('GET', '/');
        $server = $this->buildServer($request);
        $server->directCall(__NAMESPACE__ . '\\StubController', 'ok', []);
        $this->assertTrue(true);
    }

    public function testDirectCallThrowsForNonexistentController(): void
    {
        $request = $this->buildRequest('GET', '/');
        $server = $this->buildServer($request);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('does not exist');
        $server->directCall('Nonexistent\\Controller', 'action', []);
    }

    public function testDirectCallThrowsForNonexistentAction(): void
    {
        $request = $this->buildRequest('GET', '/');
        $server = $this->buildServer($request);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('does not exist');
        $server->directCall(__NAMESPACE__ . '\\StubController', 'nonexistent', []);
    }

    // --- Bearer auth ---

    public function testBearerTokenAuthSucceeds(): void
    {
        $config = $this->createMockConfig([
            'rest' => ['server' => ['api_key' => 'secret-bearer-key']],
        ]);
        Registry::getInstance()->add('config', $config);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secret-bearer-key';
        $request = $this->buildRequest('GET', '/test');
        $router = new Router();
        $router->addRoute(new Router\Route('test', 'GET', __NAMESPACE__, 'StubController', 'ok'));
        $response = $this->buildServer($request, $router)->run();
        $this->assertSame(200, $response->getCode());
    }
}

/**
 * Minimal stub controller for Server tests.
 */
class StubController
{
    public function ok($parameters = [])
    {
        return ['ok' => true];
    }

    public function fail($parameters = [])
    {
        throw new \Exception('test error');
    }

    public function redirect($parameters = [])
    {
        return ['redirect' => 'https://example.com', 'ok' => true];
    }
}
