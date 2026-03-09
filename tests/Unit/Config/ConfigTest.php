<?php

namespace Pressmind\Tests\Unit\Config;

use Pressmind\Config;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Pressmind\Config (delegation to adapter).
 */
class ConfigTest extends AbstractTestCase
{
    public function testConfigConstructWithJsonAdapter(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'pm_cfg_') . '.json';
        file_put_contents($file, json_encode([
            'development' => ['a' => 1],
            'production'  => [],
            'testing'     => [],
        ]));

        $config = new Config('json', $file, 'development');
        $data = $config->read();

        $this->assertIsArray($data);
        $this->assertSame(1, $data['a']);

        @unlink($file);
    }

    public function testConfigConstructWithPhpAdapter(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'pm_cfg_');
        file_put_contents($file, '<?php $config = ["development" => ["b" => 2], "production" => [], "testing" => []];');

        $config = new Config('php', $file, 'development');
        $data = $config->read();

        $this->assertIsArray($data);
        $this->assertSame(2, $data['b']);

        @unlink($file);
    }

    public function testConfigReadDelegatesToAdapter(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'pm_cfg_') . '.json';
        file_put_contents($file, json_encode([
            'development' => ['cache' => ['enabled' => true]],
            'production'  => [],
            'testing'     => [],
        ]));

        $config = new Config('json', $file, 'development');
        $this->assertTrue($config->read()['cache']['enabled']);

        @unlink($file);
    }

    public function testConfigWriteDelegatesToAdapter(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'pm_cfg_') . '.json';
        file_put_contents($file, json_encode([
            'development' => [],
            'production'  => [],
            'testing'     => [],
        ]));

        $config = new Config('json', $file, 'development');
        $config->write(['written' => true]);

        $config2 = new Config('json', $file, 'development');
        $this->assertTrue($config2->read()['written']);

        @unlink($file);
    }
}
