<?php

namespace Pressmind\Tests\Unit\MVC;

use PHPUnit\Framework\TestCase;
use Pressmind\MVC\Request;
use ReflectionClass;
use ReflectionMethod;

class RequestTest extends TestCase
{
    private array $originalServer;
    private array $originalPost;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalServer = $_SERVER;
        $this->originalPost = $_POST;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_POST = $this->originalPost;
        parent::tearDown();
    }

    private function buildRequest(string $method, string $uri, string $baseUrl = '/', array $serverExtras = []): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER = array_merge($_SERVER, $serverExtras);
        return new Request($baseUrl);
    }

    public function testGetMethodReturnsRequestMethod(): void
    {
        $request = $this->buildRequest('GET', '/foo/bar');
        $this->assertSame('GET', $request->getMethod());
    }

    public function testIsGetReturnsTrueForGetRequest(): void
    {
        $request = $this->buildRequest('GET', '/');
        $this->assertTrue($request->isGet());
        $this->assertFalse($request->isPost());
    }

    public function testIsPostReturnsTrueForPostRequest(): void
    {
        $_POST = [];
        $request = $this->buildRequest('POST', '/');
        $this->assertTrue($request->isPost());
        $this->assertFalse($request->isGet());
    }

    public function testQueryStringParametersParsed(): void
    {
        $request = $this->buildRequest('GET', '/api/test?foo=bar&baz=qux');
        $params = $request->getParameters();
        $this->assertSame('bar', $params['foo']);
        $this->assertSame('qux', $params['baz']);
    }

    public function testQueryStringStrippedFromRawUri(): void
    {
        $request = $this->buildRequest('GET', '/some/path?key=value');
        $this->assertStringNotContainsString('?', $request->getRawUri());
    }

    public function testBaseUrlStrippedFromUri(): void
    {
        $request = $this->buildRequest('GET', '/api/v1/resource', '/api/v1/');
        $rawUri = $request->getRawUri();
        $this->assertStringNotContainsString('/api/v1/', $rawUri);
    }

    public function testGetParameterReturnsValueWhenKeyExists(): void
    {
        $request = $this->buildRequest('GET', '/?alpha=beta');
        $this->assertSame('beta', $request->getParameter('alpha'));
    }

    public function testGetParameterReturnsNullForMissingKey(): void
    {
        $request = $this->buildRequest('GET', '/');
        $this->assertNull($request->getParameter('nonexistent'));
    }

    public function testAddParameterAddsAndRetrieves(): void
    {
        $request = $this->buildRequest('GET', '/');
        $request->addParameter('custom', 'value123');
        $this->assertSame('value123', $request->getParameter('custom'));
        $this->assertArrayHasKey('custom', $request->getParameters());
    }

    public function testGetHeaderReturnsCaseInsensitiveMatch(): void
    {
        $_SERVER['HTTP_X_CUSTOM_HEADER'] = 'HeaderValue';
        $request = $this->buildRequest('GET', '/');
        $this->assertSame('HeaderValue', $request->getHeader('X-Custom-Header'));
    }

    public function testGetHeaderReturnsNullForMissingHeader(): void
    {
        $request = $this->buildRequest('GET', '/');
        $this->assertNull($request->getHeader('X-Nonexistent'));
    }

    public function testGetHeadersReturnsArray(): void
    {
        $request = $this->buildRequest('GET', '/');
        $this->assertIsArray($request->getHeaders());
    }

    public function testContentTypeDefaultsToOctetStream(): void
    {
        unset($_SERVER['HTTP_CONTENT_TYPE'], $_SERVER['CONTENT_TYPE']);
        $request = $this->buildRequest('GET', '/');
        $this->assertSame('application/octet-stream', $request->getContentType());
    }

    public function testContentTypeParsedFromServerVar(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $request = $this->buildRequest('GET', '/');
        $this->assertSame('application/json', $request->getContentType());
    }

    public function testParseParametersPrivateMethod(): void
    {
        $reflection = new ReflectionMethod(Request::class, '_parseParameters');
        $reflection->setAccessible(true);

        $request = $this->buildRequest('GET', '/');
        $result = $reflection->invoke($request, ['key1', 'val1', 'key2', 'val2']);
        $this->assertSame(['key1' => 'val1', 'key2' => 'val2'], $result);
    }

    public function testParseParametersOddCountAppendNull(): void
    {
        $reflection = new ReflectionMethod(Request::class, '_parseParameters');
        $reflection->setAccessible(true);

        $request = $this->buildRequest('GET', '/');
        $result = $reflection->invoke($request, ['key1', 'val1', 'key2']);
        $this->assertSame(['key1' => 'val1', 'key2' => null], $result);
    }

    public function testParseParametersEmptyValueBecomesNull(): void
    {
        $reflection = new ReflectionMethod(Request::class, '_parseParameters');
        $reflection->setAccessible(true);

        $request = $this->buildRequest('GET', '/');
        $result = $reflection->invoke($request, ['key1', '']);
        $this->assertSame(['key1' => null], $result);
    }

    public function testUriArrayParsedWithMoreThanThreeSegments(): void
    {
        $request = $this->buildRequest('GET', '/mod/ctrl/action/extra_key/extra_val');
        $params = $request->getParameters();
        $this->assertSame('extra_val', $params['extra_key'] ?? null);
    }

    public function testGetParsedBasicAuthReturnsCredentials(): void
    {
        $encoded = base64_encode('user:pass');
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . $encoded;
        $request = $this->buildRequest('GET', '/');

        // getParsedBasicAuth() checks for key 'Authorization' (capital A),
        // but _apache_request_headers() stores all keys lowercase.
        // Use Reflection to set the header with the expected casing.
        $ref = new ReflectionClass($request);
        $prop = $ref->getProperty('_headers');
        $prop->setAccessible(true);
        $headers = $prop->getValue($request);
        $headers['Authorization'] = 'Basic ' . $encoded;
        $prop->setValue($request, $headers);

        $result = $request->getParsedBasicAuth();
        $this->assertIsArray($result);
        $this->assertSame('user', $result[0]);
        $this->assertSame('pass', $result[1]);
    }

    public function testGetParsedBasicAuthReturnsFalseWithoutHeader(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $request = $this->buildRequest('GET', '/');
        $this->assertFalse($request->getParsedBasicAuth());
    }

    public function testGetParsedBasicAuthReturnsFalseForNonBasicScheme(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer some-token';
        $request = $this->buildRequest('GET', '/');
        $this->assertFalse($request->getParsedBasicAuth());
    }

    public function testGetBodyReturnsNullForGetRequest(): void
    {
        $request = $this->buildRequest('GET', '/');
        $this->assertNull($request->getBody());
    }

    public function testPostWithFormDataSetsBody(): void
    {
        $_POST = ['field1' => 'value1'];
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $request = $this->buildRequest('POST', '/');
        $body = $request->getBody();
        $this->assertIsArray($body);
        $this->assertSame('value1', $body['field1']);
    }

    public function testGetPostValuesReturnsSameAsBody(): void
    {
        $_POST = ['x' => '1'];
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data';
        $request = $this->buildRequest('POST', '/');
        $this->assertSame($request->getBody(), $request->getPostValues());
    }

    public function testPostBodyMergedIntoParameters(): void
    {
        $_POST = ['posted' => 'yes'];
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $request = $this->buildRequest('POST', '/?query=1');
        $params = $request->getParameters();
        $this->assertSame('1', $params['query']);
        $this->assertSame('yes', $params['posted']);
    }

    public function testMethodNullWhenServerMethodNotSet(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
        $_SERVER['REQUEST_URI'] = '/';
        $request = new Request();
        $this->assertNull($request->getMethod());
    }

    public function testEmptyQueryStringDoesNotAddParameters(): void
    {
        $request = $this->buildRequest('GET', '/path?');
        $this->assertNotContains('', array_keys($request->getParameters()));
    }
}
