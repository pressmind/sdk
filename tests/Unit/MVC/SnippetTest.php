<?php

namespace Pressmind\Tests\Unit\MVC;

use Exception;
use PHPUnit\Framework\TestCase;
use Pressmind\MVC\Dispatcher;
use Pressmind\MVC\Request;
use Pressmind\MVC\Snippet;
use ReflectionClass;

class SnippetTest extends TestCase
{
    private string $snippetDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->snippetDir = APPLICATION_PATH . DIRECTORY_SEPARATOR . 'Standard'
            . DIRECTORY_SEPARATOR . 'Snippet';
        if (!is_dir($this->snippetDir)) {
            mkdir($this->snippetDir, 0755, true);
        }

        $ref = new ReflectionClass(Dispatcher::class);
        $prop = $ref->getProperty('_instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $dispatcher = Dispatcher::getInstance();
        $mockRequest = $this->createMock(Request::class);
        $mockRequest->method('getParameter')
            ->willReturnCallback(function ($key) {
                $map = ['module' => 'standard'];
                return $map[$key] ?? null;
            });
        $dispatcher->setRequest($mockRequest);
    }

    protected function tearDown(): void
    {
        $files = glob($this->snippetDir . DIRECTORY_SEPARATOR . '*.php');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $ref = new ReflectionClass(Dispatcher::class);
        $prop = $ref->getProperty('_instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        parent::tearDown();
    }

    public function testRenderWithValidSnippet(): void
    {
        $snippetContent = '<?php echo "snippet-output"; ?>';
        file_put_contents($this->snippetDir . DIRECTORY_SEPARATOR . 'TestSnippet.php', $snippetContent);

        $result = Snippet::render('testSnippet');
        $this->assertSame('snippet-output', $result);
    }

    public function testRenderPassesDataToSnippet(): void
    {
        $snippetContent = '<?php echo $data["message"]; ?>';
        file_put_contents($this->snippetDir . DIRECTORY_SEPARATOR . 'DataSnippet.php', $snippetContent);

        $result = Snippet::render('dataSnippet', ['message' => 'hello-from-data']);
        $this->assertSame('hello-from-data', $result);
    }

    public function testRenderThrowsExceptionForMissingSnippet(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('does not exist');
        Snippet::render('nonExistentSnippet');
    }

    public function testRenderUsesOutputBuffering(): void
    {
        $snippetContent = '<?php echo "buffered-snippet"; ?>';
        file_put_contents($this->snippetDir . DIRECTORY_SEPARATOR . 'BufferSnippet.php', $snippetContent);

        $levelBefore = ob_get_level();
        $result = Snippet::render('bufferSnippet');
        $levelAfter = ob_get_level();

        $this->assertSame($levelBefore, $levelAfter);
        $this->assertSame('buffered-snippet', $result);
    }

    public function testRenderWithNullData(): void
    {
        $snippetContent = '<?php echo is_null($data) ? "null-data" : "has-data"; ?>';
        file_put_contents($this->snippetDir . DIRECTORY_SEPARATOR . 'NullDataSnippet.php', $snippetContent);

        $result = Snippet::render('nullDataSnippet', null);
        $this->assertSame('null-data', $result);
    }

    public function testRenderWithHtmlSnippet(): void
    {
        $snippetContent = '<span><?php echo $data["label"]; ?></span>';
        file_put_contents($this->snippetDir . DIRECTORY_SEPARATOR . 'HtmlSnippet.php', $snippetContent);

        $result = Snippet::render('htmlSnippet', ['label' => 'Click Me']);
        $this->assertSame('<span>Click Me</span>', $result);
    }
}
