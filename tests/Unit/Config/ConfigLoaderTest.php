<?php

namespace Pressmind\Tests\Unit\Config;

use Pressmind\Config\Adapter\Php as PhpAdapter;
use Pressmind\Config\Adapter\Json as JsonAdapter;
use Pressmind\Config\AdapterInterface;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Tests for config loading via Php and Json adapters.
 *
 * Fixture files live in tests/fixtures/configs/ and follow the convention:
 *   - PHP fixtures: return a flat config array (not wrapped in environment keys).
 *   - The Php adapter expects a $config variable with environment keys set via include.
 */
class ConfigLoaderTest extends AbstractTestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureDir = dirname(__DIR__, 2) . '/Fixtures/configs';
    }

    // ------------------------------------------------------------------
    // Fixture file validation
    // ------------------------------------------------------------------

    /**
     * @dataProvider fixtureFileProvider
     */
    public function testFixtureFileReturnsArray(string $filename): void
    {
        $path = $this->fixtureDir . '/' . $filename;
        $this->assertFileExists($path);

        $result = require $path;

        $this->assertIsArray($result, $filename . ' must return an array');
    }

    /**
     * @dataProvider fixtureFileProvider
     */
    public function testFixtureFileContainsRequiredKeys(string $filename): void
    {
        $result = require $this->fixtureDir . '/' . $filename;

        // Only baseline/minimal fixtures are guaranteed to have these keys;
        // override configs intentionally omit them.
        if ($this->isOverrideFixture($filename)) {
            $this->assertIsArray($result, 'Override fixture must still return an array');
            return;
        }

        $this->assertArrayHasKey('cache', $result, $filename . ' must contain "cache" key');
        $this->assertArrayHasKey('logging', $result, $filename . ' must contain "logging" key');
    }

    public static function fixtureFileProvider(): array
    {
        $dir = dirname(__DIR__, 2) . '/Fixtures/configs';
        $files = glob($dir . '/config_*.php');

        $data = [];
        foreach ($files as $file) {
            $basename = basename($file);
            $data[$basename] = [$basename];
        }
        return $data;
    }

    // ------------------------------------------------------------------
    // Php adapter
    // ------------------------------------------------------------------

    public function testPhpAdapterImplementsInterface(): void
    {
        $adapter = new PhpAdapter($this->createPhpConfigFile([]), 'development');
        $this->assertInstanceOf(AdapterInterface::class, $adapter);
    }

    public function testPhpAdapterReadsDevelopmentEnvironment(): void
    {
        $devConfig = ['cache' => ['enabled' => false], 'logging' => ['enable_advanced_object_log' => false]];
        $file = $this->createPhpConfigFile($devConfig);
        $adapter = new PhpAdapter($file, 'development');

        $result = $adapter->read();

        $this->assertSame(false, $result['cache']['enabled']);
    }

    public function testPhpAdapterProductionMergesDevelopment(): void
    {
        $devConfig = ['cache' => ['enabled' => false], 'site_name' => 'dev'];
        $prodOverride = ['site_name' => 'prod'];
        $file = $this->createPhpConfigFile($devConfig, $prodOverride);
        $adapter = new PhpAdapter($file, 'production');

        $result = $adapter->read();

        $this->assertSame('prod', $result['site_name'], 'Production must override development values');
        $this->assertArrayHasKey('cache', $result, 'Production must inherit development keys');
    }

    public function testPhpAdapterReadAllEnvironments(): void
    {
        $devConfig = ['key' => 'dev_value'];
        $prodConfig = ['key' => 'prod_value'];
        $file = $this->createPhpConfigFile($devConfig, $prodConfig);
        $adapter = new PhpAdapter($file, 'development');

        $all = $adapter->readAllEnvironments();

        $this->assertArrayHasKey('development', $all);
        $this->assertArrayHasKey('production', $all);
        $this->assertArrayHasKey('testing', $all);
        $this->assertSame('dev_value', $all['development']['key']);
        $this->assertSame('prod_value', $all['production']['key']);
    }

    public function testPhpAdapterDefaultsToDevelopment(): void
    {
        $devConfig = ['env' => 'dev'];
        $file = $this->createPhpConfigFile($devConfig);
        $adapter = new PhpAdapter($file);

        $result = $adapter->read();

        $this->assertSame('dev', $result['env']);
    }

    // ------------------------------------------------------------------
    // Json adapter
    // ------------------------------------------------------------------

    public function testJsonAdapterImplementsInterface(): void
    {
        $adapter = new JsonAdapter($this->createJsonConfigFile([]), 'development');
        $this->assertInstanceOf(AdapterInterface::class, $adapter);
    }

    public function testJsonAdapterReadsDevelopmentEnvironment(): void
    {
        $devConfig = ['cache' => ['enabled' => true], 'logging' => ['level' => 'debug']];
        $file = $this->createJsonConfigFile($devConfig);
        $adapter = new JsonAdapter($file, 'development');

        $result = $adapter->read();

        $this->assertTrue($result['cache']['enabled']);
        $this->assertSame('debug', $result['logging']['level']);
    }

    public function testJsonAdapterProductionMergesDevelopment(): void
    {
        $devConfig = ['cache' => ['enabled' => false], 'site' => 'dev'];
        $prodOverride = ['site' => 'prod'];
        $file = $this->createJsonConfigFile($devConfig, $prodOverride);
        $adapter = new JsonAdapter($file, 'production');

        $result = $adapter->read();

        $this->assertSame('prod', $result['site']);
        $this->assertArrayHasKey('cache', $result);
    }

    public function testJsonAdapterReadAllEnvironments(): void
    {
        $devConfig = ['key' => 'dev'];
        $prodConfig = ['key' => 'prod'];
        $file = $this->createJsonConfigFile($devConfig, $prodConfig);
        $adapter = new JsonAdapter($file, 'development');

        $all = $adapter->readAllEnvironments();

        $this->assertCount(3, $all);
        $this->assertSame('dev', $all['development']['key']);
        $this->assertSame('prod', $all['production']['key']);
    }

    public function testJsonAdapterDefaultsToDevelopment(): void
    {
        $devConfig = ['env' => 'dev'];
        $file = $this->createJsonConfigFile($devConfig);
        $adapter = new JsonAdapter($file);

        $result = $adapter->read();

        $this->assertSame('dev', $result['env']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function isOverrideFixture(string $filename): bool
    {
        return str_contains($filename, 'override') || str_contains($filename, 'subsite');
    }

    /**
     * Create a temporary PHP config file compatible with the Php adapter.
     * The adapter does `include_once` and expects a $config variable with environment keys.
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

    /**
     * Create a temporary JSON config file compatible with the Json adapter.
     */
    private function createJsonConfigFile(array $devConfig, array $prodConfig = [], array $testConfig = []): string
    {
        $file = tempnam(sys_get_temp_dir(), 'pm_cfg_') . '.json';
        $data = [
            'development' => $devConfig,
            'production'  => $prodConfig,
            'testing'     => $testConfig,
        ];
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
        return $file;
    }
}
