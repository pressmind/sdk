<?php

namespace Pressmind\Tests\Unit\Cache\Adapter;

use Pressmind\Cache\Adapter\AdapterInterface;
use Pressmind\Cache\Adapter\Factory;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class FactoryTest extends AbstractTestCase
{
    public function testCreateRedisReturnsAdapterInterface(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not loaded');
        }
        $host = getenv('REDIS_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('REDIS_PORT') ?: 6379);
        try {
            $probe = new \Redis();
            $probe->connect($host, $port, 1);
            $probe->close();
        } catch (\RedisException $e) {
            $this->markTestSkipped('Redis server not reachable: ' . $e->getMessage());
        }
        $config = $this->createMockConfig([
            'cache' => [
                'adapter' => [
                    'config' => [
                        'host' => $host,
                        'port' => $port,
                        'password' => '',
                    ],
                ],
                'key_prefix' => 'pm_unit_test_',
                'max_idle_time' => 3600,
                'update_frequency' => 86400,
            ],
        ]);
        Registry::getInstance()->add('config', $config);
        $adapter = Factory::create('Redis');
        $this->assertInstanceOf(AdapterInterface::class, $adapter);
    }

    public function testCreateInvalidAdapterThrows(): void
    {
        $this->expectException(\Throwable::class);
        Factory::create('NonExistentAdapter');
    }
}
