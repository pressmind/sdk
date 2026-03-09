<?php

namespace Pressmind\Tests\Unit\MVC;

use PHPUnit\Framework\TestCase;
use Pressmind\MVC\AbstractController;

class ConcreteController extends AbstractController
{
    public function indexAction(): string
    {
        return 'index';
    }
}

class AbstractControllerTest extends TestCase
{
    private function createController(array $params = []): ConcreteController
    {
        $defaults = [
            'module' => 'standard',
            'controller' => 'index',
            'action' => 'index',
        ];
        return new ConcreteController(array_merge($defaults, $params));
    }

    public function testGetParameterReturnsValue(): void
    {
        $controller = $this->createController(['foo' => 'bar']);
        $this->assertSame('bar', $controller->getParameter('foo'));
    }

    public function testGetParameterReturnsNullForMissing(): void
    {
        $controller = $this->createController();
        $this->assertNull($controller->getParameter('nonexistent'));
    }

    public function testGetParameterReturnsDefaultForMissing(): void
    {
        $controller = $this->createController();
        $this->assertSame('fallback', $controller->getParameter('missing', 'fallback'));
    }

    public function testGetParameterReturnsDefaultForEmpty(): void
    {
        $controller = $this->createController(['empty_key' => '']);
        $this->assertSame('default', $controller->getParameter('empty_key', 'default'));
    }

    public function testSetParameterAddsNewKey(): void
    {
        $controller = $this->createController();
        $controller->setParameter('new_key', 'new_value');
        $this->assertSame('new_value', $controller->getParameter('new_key'));
    }

    public function testSetParameterOverwritesExisting(): void
    {
        $controller = $this->createController(['key' => 'old']);
        $controller->setParameter('key', 'new');
        $this->assertSame('new', $controller->getParameter('key'));
    }

    public function testParametersPropertyAccessible(): void
    {
        $params = ['module' => 'admin', 'controller' => 'users', 'action' => 'list', 'page' => '3'];
        $controller = $this->createController($params);
        $this->assertSame('admin', $controller->parameters['module']);
        $this->assertSame('3', $controller->parameters['page']);
    }

    public function testAddHeaderScriptAddsScript(): void
    {
        $controller = $this->createController();
        $controller->addHeaderScript('console.log("hello");');
        $html = $controller->renderHeaderScripts();
        $this->assertStringContainsString('<script>console.log("hello");</script>', $html);
    }

    public function testAddHeaderScriptDeduplicates(): void
    {
        $controller = $this->createController();
        $controller->addHeaderScript('var x = 1;');
        $controller->addHeaderScript('var x = 1;');
        $html = $controller->renderHeaderScripts();
        $this->assertSame(1, substr_count($html, '<script>var x = 1;</script>'));
    }

    public function testAddDifferentHeaderScripts(): void
    {
        $controller = $this->createController();
        $controller->addHeaderScript('var a = 1;');
        $controller->addHeaderScript('var b = 2;');
        $html = $controller->renderHeaderScripts();
        $this->assertStringContainsString('var a = 1;', $html);
        $this->assertStringContainsString('var b = 2;', $html);
    }

    public function testRenderHeaderScriptsEmptyWhenNoneAdded(): void
    {
        $controller = $this->createController();
        $this->assertSame('', $controller->renderHeaderScripts());
    }

    public function testAddHeaderScriptInclude(): void
    {
        $controller = $this->createController();
        $controller->addHeaderScriptInclude('/js/app.js', ['defer' => 'defer']);
        $html = $controller->renderHeaderScriptIncludes();
        $this->assertStringContainsString('src="/js/app.js"', $html);
        $this->assertStringContainsString('defer="defer"', $html);
    }

    public function testAddHeaderScriptIncludeDeduplicates(): void
    {
        $controller = $this->createController();
        $controller->addHeaderScriptInclude('/js/app.js');
        $controller->addHeaderScriptInclude('/js/app.js');
        $html = $controller->renderHeaderScriptIncludes();
        $this->assertSame(1, substr_count($html, '/js/app.js'));
    }

    public function testAddFooterScriptInclude(): void
    {
        $controller = $this->createController();
        $controller->addFooterScriptInclude('/js/footer.js', ['async' => 'async']);
        $html = $controller->renderFooterScriptIncludes();
        $this->assertStringContainsString('src="/js/footer.js"', $html);
        $this->assertStringContainsString('async="async"', $html);
    }

    public function testAddFooterScriptIncludeDeduplicates(): void
    {
        $controller = $this->createController();
        $controller->addFooterScriptInclude('/js/footer.js');
        $controller->addFooterScriptInclude('/js/footer.js');
        $html = $controller->renderFooterScriptIncludes();
        $this->assertSame(1, substr_count($html, '/js/footer.js'));
    }

    public function testAddCssStyleInclude(): void
    {
        $controller = $this->createController();
        $controller->addCssStyleInclude('/css/style.css', ['media' => 'screen']);
        $html = $controller->renderCssStyleIncludes();
        $this->assertStringContainsString('href="/css/style.css"', $html);
        $this->assertStringContainsString('media="screen"', $html);
        $this->assertStringContainsString('rel="stylesheet"', $html);
    }

    public function testAddCssStyleIncludeDeduplicates(): void
    {
        $controller = $this->createController();
        $controller->addCssStyleInclude('/css/style.css');
        $controller->addCssStyleInclude('/css/style.css');
        $html = $controller->renderCssStyleIncludes();
        $this->assertSame(1, substr_count($html, '/css/style.css'));
    }

    public function testRenderCssStyleIncludesEmptyWhenNoneAdded(): void
    {
        $controller = $this->createController();
        $this->assertSame('', $controller->renderCssStyleIncludes());
    }

    public function testRenderHeaderScriptIncludesEmptyWhenNoneAdded(): void
    {
        $controller = $this->createController();
        $this->assertSame('', $controller->renderHeaderScriptIncludes());
    }

    public function testRenderFooterScriptIncludesEmptyWhenNoneAdded(): void
    {
        $controller = $this->createController();
        $this->assertSame('', $controller->renderFooterScriptIncludes());
    }

    public function testAddHttpHeader(): void
    {
        $controller = $this->createController();
        $controller->addHttpHeader('X-Custom', 'Value');
        $ref = new \ReflectionClass(AbstractController::class);
        $prop = $ref->getProperty('_httpHeaders');
        $prop->setAccessible(true);
        $headers = $prop->getValue($controller);
        $this->assertSame('Value', $headers['X-Custom']);
    }

    public function testInitMethodExists(): void
    {
        $controller = $this->createController();
        $controller->init();
        $this->assertTrue(true);
    }

    public function testMultipleScriptIncludesWithAttributes(): void
    {
        $controller = $this->createController();
        $controller->addHeaderScriptInclude('/js/one.js', ['type' => 'module']);
        $controller->addHeaderScriptInclude('/js/two.js', ['defer' => 'defer', 'crossorigin' => 'anonymous']);
        $html = $controller->renderHeaderScriptIncludes();
        $this->assertStringContainsString('type="module"', $html);
        $this->assertStringContainsString('crossorigin="anonymous"', $html);
    }
}
