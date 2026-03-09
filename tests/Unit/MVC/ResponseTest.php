<?php

namespace Pressmind\Tests\Unit\MVC;

use PHPUnit\Framework\TestCase;
use Pressmind\MVC\Response;
use ReflectionClass;

class ResponseTest extends TestCase
{
    private function getProperty(Response $response, string $property)
    {
        $ref = new ReflectionClass($response);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($response);
    }

    public function testDefaultCodeIs200(): void
    {
        $response = new Response();
        $this->assertSame(200, $this->getProperty($response, '_code'));
    }

    public function testDefaultHeadersEmpty(): void
    {
        $response = new Response();
        $this->assertSame([], $this->getProperty($response, '_headers'));
    }

    public function testSetBodyStoresContent(): void
    {
        $response = new Response();
        $response->setBody('Hello World');
        $this->assertSame('Hello World', $this->getProperty($response, '_body'));
    }

    public function testSetBodyAcceptsArray(): void
    {
        $response = new Response();
        $data = ['key' => 'value'];
        $response->setBody($data);
        $this->assertSame($data, $this->getProperty($response, '_body'));
    }

    public function testSetCodeChangesStatusCode(): void
    {
        $response = new Response();
        $response->setCode(404);
        $this->assertSame(404, $this->getProperty($response, '_code'));
    }

    public function testSetContentTypeStoresValue(): void
    {
        $response = new Response();
        $response->setContentType('application/json');
        $this->assertSame('application/json', $this->getProperty($response, '_content_type'));
    }

    public function testSetContentEncodingStoresValue(): void
    {
        $response = new Response();
        $response->setContentEncoding('gzip');
        $this->assertSame('gzip', $this->getProperty($response, '_content_encoding'));
    }

    public function testAddHeaderStoresKeyValuePair(): void
    {
        $response = new Response();
        $response->addHeader('X-Custom', 'TestValue');
        $headers = $this->getProperty($response, '_headers');
        $this->assertSame('TestValue', $headers['X-Custom']);
    }

    public function testAddHeaderOverwritesSameKey(): void
    {
        $response = new Response();
        $response->addHeader('X-Custom', 'First');
        $response->addHeader('X-Custom', 'Second');
        $headers = $this->getProperty($response, '_headers');
        $this->assertSame('Second', $headers['X-Custom']);
    }

    public function testMultipleHeadersStoredIndependently(): void
    {
        $response = new Response();
        $response->addHeader('X-One', '1');
        $response->addHeader('X-Two', '2');
        $headers = $this->getProperty($response, '_headers');
        $this->assertCount(2, $headers);
        $this->assertSame('1', $headers['X-One']);
        $this->assertSame('2', $headers['X-Two']);
    }

    public function testErrorMessagesContain404And500(): void
    {
        $response = new Response();
        $messages = $this->getProperty($response, '_error_messages');
        $this->assertArrayHasKey(404, $messages);
        $this->assertArrayHasKey(500, $messages);
        $this->assertIsString($messages[404]);
        $this->assertIsString($messages[500]);
    }

    public function testSetCodeAcceptsVariousHttpCodes(): void
    {
        $response = new Response();
        $codes = [200, 201, 301, 400, 401, 403, 404, 500, 503];
        foreach ($codes as $code) {
            $response->setCode($code);
            $this->assertSame($code, $this->getProperty($response, '_code'));
        }
    }
}
