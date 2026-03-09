<?php

namespace Pressmind\Tests\Unit\MVC;

use Exception;
use PHPUnit\Framework\TestCase;
use Pressmind\MVC\View;
use ReflectionClass;

class ViewTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pressmind_view_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir . DIRECTORY_SEPARATOR . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    private function getProperty(View $view, string $property)
    {
        $ref = new ReflectionClass($view);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($view);
    }

    public function testConstructorWithNullPath(): void
    {
        $view = new View();
        $this->assertNull($this->getProperty($view, '_view_script'));
    }

    public function testConstructorWithPath(): void
    {
        $view = new View('/some/path');
        $this->assertSame('/some/path', $this->getProperty($view, '_view_script'));
    }

    public function testSetViewScript(): void
    {
        $view = new View();
        $view->setViewScript('/new/path');
        $this->assertSame('/new/path', $this->getProperty($view, '_view_script'));
    }

    public function testSetDataWithArray(): void
    {
        $view = new View();
        $data = ['key' => 'value', 'foo' => 'bar'];
        $view->setData($data);
        $this->assertSame($data, $this->getProperty($view, '_data'));
    }

    public function testSetDataWithObject(): void
    {
        $view = new View();
        $obj = new \stdClass();
        $obj->key = 'value';
        $view->setData($obj);
        $stored = $this->getProperty($view, '_data');
        $this->assertIsObject($stored);
        $this->assertSame('value', $stored->key);
    }

    public function testSetDataWithScalarCreatesKeyValueArray(): void
    {
        $view = new View();
        $view->setData('myKey', 'myValue');
        $this->assertSame(['myKey' => 'myValue'], $this->getProperty($view, '_data'));
    }

    public function testRenderWithTempFile(): void
    {
        $templateContent = '<?php echo "Hello " . $data["name"]; ?>';
        $templatePath = $this->tempDir . DIRECTORY_SEPARATOR . 'TestTemplate';
        file_put_contents($templatePath . '.php', $templateContent);

        $view = new View();
        $view->setViewScript($templatePath);
        $result = $view->render(['name' => 'World']);

        $this->assertSame('Hello World', $result);
    }

    public function testRenderThrowsExceptionForMissingTemplate(): void
    {
        $view = new View();
        $view->setViewScript('/nonexistent/path/Template');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('does not exist');
        $view->render();
    }

    public function testRenderUsesOutputBuffering(): void
    {
        $templateContent = '<?php echo "buffered"; ?>';
        $templatePath = $this->tempDir . DIRECTORY_SEPARATOR . 'BufferTest';
        file_put_contents($templatePath . '.php', $templateContent);

        $view = new View();
        $view->setViewScript($templatePath);

        $levelBefore = ob_get_level();
        $result = $view->render();
        $levelAfter = ob_get_level();

        $this->assertSame($levelBefore, $levelAfter);
        $this->assertSame('buffered', $result);
    }

    public function testRenderSetsDataBeforeInclude(): void
    {
        $templateContent = '<?php echo isset($data) ? "data-set" : "no-data"; ?>';
        $templatePath = $this->tempDir . DIRECTORY_SEPARATOR . 'DataCheck';
        file_put_contents($templatePath . '.php', $templateContent);

        $view = new View();
        $view->setViewScript($templatePath);
        $result = $view->render(['test' => true]);

        $this->assertSame('data-set', $result);
    }

    public function testRenderWithHtmlTemplate(): void
    {
        $templateContent = '<div class="test"><?php echo $data["title"]; ?></div>';
        $templatePath = $this->tempDir . DIRECTORY_SEPARATOR . 'HtmlTest';
        file_put_contents($templatePath . '.php', $templateContent);

        $view = new View();
        $view->setViewScript($templatePath);
        $result = $view->render(['title' => 'Page Title']);

        $this->assertSame('<div class="test">Page Title</div>', $result);
    }
}
