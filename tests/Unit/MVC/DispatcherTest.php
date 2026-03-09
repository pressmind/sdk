<?php

namespace Pressmind\Tests\Unit\MVC;

use PHPUnit\Framework\TestCase;
use Pressmind\MVC\Dispatcher;
use Pressmind\MVC\Request;
use Pressmind\MVC\Response;
use Pressmind\MVC\Router;
use ReflectionClass;

class DispatcherTest extends TestCase
{
    protected function tearDown(): void
    {
        $ref = new ReflectionClass(Dispatcher::class);
        $prop = $ref->getProperty('_instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
        parent::tearDown();
    }

    private function getProperty(Dispatcher $dispatcher, string $property)
    {
        $ref = new ReflectionClass($dispatcher);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($dispatcher);
    }

    public function testConstructorSetsDefaultResponse(): void
    {
        $dispatcher = new Dispatcher();
        $response = $dispatcher->getResponse();
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testGetInstanceReturnsSingleton(): void
    {
        $instance1 = Dispatcher::getInstance();
        $instance2 = Dispatcher::getInstance();
        $this->assertSame($instance1, $instance2);
    }

    public function testGetInstanceReturnsDispatcher(): void
    {
        $instance = Dispatcher::getInstance();
        $this->assertInstanceOf(Dispatcher::class, $instance);
    }

    public function testSetAndGetRequest(): void
    {
        $dispatcher = new Dispatcher();
        $request = $this->createMock(Request::class);
        $dispatcher->setRequest($request);
        $this->assertSame($request, $dispatcher->getRequest());
    }

    public function testSetAndGetResponse(): void
    {
        $dispatcher = new Dispatcher();
        $response = new Response();
        $dispatcher->setResponse($response);
        $this->assertSame($response, $dispatcher->getResponse());
    }

    public function testSetAndGetRouter(): void
    {
        $dispatcher = new Dispatcher();
        $router = new Router();
        $dispatcher->setRouter($router);
        $this->assertSame($router, $dispatcher->getRouter());
    }

    public function testGetRequestReturnsNullBeforeSet(): void
    {
        $dispatcher = new Dispatcher();
        $this->assertNull($dispatcher->getRequest());
    }

    public function testGetRouterReturnsNullBeforeSet(): void
    {
        $dispatcher = new Dispatcher();
        $this->assertNull($dispatcher->getRouter());
    }

    public function testDisableLayoutSetsFlag(): void
    {
        $dispatcher = new Dispatcher();
        $dispatcher->disableLayout();
        $this->assertFalse($this->getProperty($dispatcher, '_layout_enabled'));
    }

    public function testLayoutEnabledByDefault(): void
    {
        $dispatcher = new Dispatcher();
        $this->assertTrue($this->getProperty($dispatcher, '_layout_enabled'));
    }
}
