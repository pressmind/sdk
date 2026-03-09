<?php

namespace Pressmind\Tests\Unit\Config;

use Pressmind\Config\Adapter\Php as PhpAdapter;
use Pressmind\Config\AdapterInterface;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Config\Adapter\Php (write, empty production/testing branches).
 * ConfigLoaderTest already covers read() and readAllEnvironments() with data; this adds write() and empty envs.
 */
class ConfigAdapterPhpTest extends AbstractTestCase
{
    /**
     * Create a temporary PHP config file compatible with the Php adapter.
     */
    private function createPhpConfigFile(array $devConfig, array $prodConfig = [], array $testConfig = []): string
    {
        $file = tempnam(sys_get_temp_dir(), 'pm_cfg_');
        $content = '<?php $config = ' . var_export([
            'development' => $devConfig,
            'production'  => $prodConfig,
            'testing'     => $testConfig,
        ], true) . ';';
        file_put_contents($file, $content);
        return $file;
    }

    public function testPhpAdapterImplementsInterface(): void
    {
        $adapter = new PhpAdapter($this->createPhpConfigFile([]), 'development');
        $this->assertInstanceOf(AdapterInterface::class, $adapter);
    }

    public function testPhpAdapterWritePersistsDataAndReadReturnsIt(): void
    {
        $file = $this->createPhpConfigFile(['a' => 1], [], []);

        $adapter = new PhpAdapter($file, 'development');
        $newData = ['cache' => ['enabled' => true], 'site' => 'updated'];
        $adapter->write($newData);

        // Use a copy so include_once in read() loads the new content (same path would skip re-include)
        $fileCopy = tempnam(sys_get_temp_dir(), 'pm_cfg_');
        copy($file, $fileCopy);
        $adapter2 = new PhpAdapter($fileCopy, 'development');
        $read = $adapter2->read();
        $this->assertSame(true, $read['cache']['enabled']);
        $this->assertSame('updated', $read['site']);

        @unlink($file);
        @unlink($fileCopy);
    }

    public function testPhpAdapterWriteOnlyOverwritesCurrentEnvironment(): void
    {
        $file = $this->createPhpConfigFile(
            ['key' => 'dev'],
            ['key' => 'prod'],
            ['key' => 'test']
        );

        $adapter = new PhpAdapter($file, 'production');
        $adapter->write(['key' => 'prod_updated', 'extra' => 'x']);

        // Use a copy so include_once loads the written content
        $fileCopy = tempnam(sys_get_temp_dir(), 'pm_cfg_');
        copy($file, $fileCopy);
        $adapter2 = new PhpAdapter($fileCopy, 'production');
        $all = $adapter2->readAllEnvironments();
        $this->assertSame('dev', $all['development']['key']);
        $this->assertSame('prod_updated', $all['production']['key']);
        $this->assertSame('x', $all['production']['extra']);
        $this->assertSame('test', $all['testing']['key']);

        @unlink($file);
        @unlink($fileCopy);
    }

    public function testPhpAdapterReadWithEmptyProductionAndTesting(): void
    {
        $devConfig = ['cache' => ['enabled' => false], 'site' => 'dev'];
        $file = $this->createPhpConfigFile($devConfig); // production and testing are []

        $adapter = new PhpAdapter($file, 'production');
        $result = $adapter->read();

        $this->assertSame(false, $result['cache']['enabled']);
        $this->assertSame('dev', $result['site']);
    }

    public function testPhpAdapterReadAllEnvironmentsWithEmptyProductionAndTesting(): void
    {
        $devConfig = ['key' => 'dev_only'];
        $file = $this->createPhpConfigFile($devConfig);

        $adapter = new PhpAdapter($file, 'development');
        $all = $adapter->readAllEnvironments();

        $this->assertSame('dev_only', $all['development']['key']);
        $this->assertSame('dev_only', $all['production']['key']);
        $this->assertSame('dev_only', $all['testing']['key']);
    }

    public function testPhpAdapterConstructorDefaultsEnvironmentToDevelopment(): void
    {
        $file = $this->createPhpConfigFile(['env' => 'dev']);
        $adapter = new PhpAdapter($file);

        $result = $adapter->read();
        $this->assertSame('dev', $result['env']);
    }

    public function testVarExportEchoModeOutputsAndReturnsNull(): void
    {
        $file = $this->createPhpConfigFile(['x' => 1]);
        $adapter = new PhpAdapter($file, 'development');

        $reflection = new \ReflectionClass($adapter);
        $method = $reflection->getMethod('_var_export');
        $method->setAccessible(true);

        ob_start();
        $result = $method->invoke($adapter, ['key' => 'val'], false);
        $output = ob_get_clean();

        $this->assertNull($result);
        $this->assertStringContainsString('key', $output);
        $this->assertStringContainsString('val', $output);

        @unlink($file);
    }
}
