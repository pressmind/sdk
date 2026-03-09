<?php

namespace Pressmind\Tests\Unit\Config;

use Pressmind\Config\Adapter\Json as JsonAdapter;
use Pressmind\Config\AdapterInterface;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Config\Adapter\Json (write, readAllEnvironments edge cases).
 * ConfigLoaderTest already covers read() and basic readAllEnvironments(); this adds write() and invalid JSON.
 */
class ConfigAdapterJsonTest extends AbstractTestCase
{
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

    public function testJsonAdapterImplementsInterface(): void
    {
        $adapter = new JsonAdapter($this->createJsonConfigFile([]), 'development');
        $this->assertInstanceOf(AdapterInterface::class, $adapter);
    }

    public function testJsonAdapterWritePersistsDataAndReadReturnsIt(): void
    {
        $initial = ['development' => ['a' => 1], 'production' => [], 'testing' => []];
        $file = tempnam(sys_get_temp_dir(), 'pm_cfg_') . '.json';
        file_put_contents($file, json_encode($initial, JSON_PRETTY_PRINT));

        $adapter = new JsonAdapter($file, 'development');
        $newData = ['cache' => ['enabled' => true], 'site' => 'updated'];
        $adapter->write($newData);

        $adapter2 = new JsonAdapter($file, 'development');
        $read = $adapter2->read();
        $this->assertSame(true, $read['cache']['enabled']);
        $this->assertSame('updated', $read['site']);

        @unlink($file);
    }

    public function testJsonAdapterWriteOnlyOverwritesCurrentEnvironment(): void
    {
        $initial = [
            'development' => ['key' => 'dev'],
            'production'  => ['key' => 'prod'],
            'testing'     => ['key' => 'test'],
        ];
        $file = tempnam(sys_get_temp_dir(), 'pm_cfg_') . '.json';
        file_put_contents($file, json_encode($initial, JSON_PRETTY_PRINT));

        $adapter = new JsonAdapter($file, 'production');
        $adapter->write(['key' => 'prod_updated', 'extra' => 'x']);

        $all = $adapter->readAllEnvironments();
        $this->assertSame('dev', $all['development']['key']);
        $this->assertSame('prod_updated', $all['production']['key']);
        $this->assertSame('x', $all['production']['extra']);
        $this->assertSame('test', $all['testing']['key']);

        @unlink($file);
    }

    public function testJsonAdapterReadAllEnvironmentsReturnsDefaultsWhenFileIsInvalidJson(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'pm_cfg_') . '.json';
        file_put_contents($file, 'not valid json {');

        $adapter = new JsonAdapter($file, 'development');
        $all = $adapter->readAllEnvironments();

        $this->assertSame(['development' => [], 'production' => [], 'testing' => []], $all);

        @unlink($file);
    }

    public function testJsonAdapterConstructorDefaultsEnvironmentToDevelopment(): void
    {
        $file = $this->createJsonConfigFile(['env' => 'dev']);
        $adapter = new JsonAdapter($file, null);

        $result = $adapter->read();
        $this->assertSame('dev', $result['env']);
    }
}
