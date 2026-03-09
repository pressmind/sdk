<?php

namespace Pressmind\Tests\Unit\REST\Controller;

use Exception;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\Redis;

class RedisTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->createMockConfig([
            'cache' => [
                'adapter' => [
                    'config' => [
                        'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
                        'port' => (int) (getenv('REDIS_PORT') ?: 6379),
                        'password' => '',
                    ],
                ],
                'key_prefix' => 'pm_unit_test_',
                'max_idle_time' => 3600,
                'update_frequency' => 86400,
            ],
        ]);
        Registry::getInstance()->add('config', $config);
    }

    public function testGetKeysThrowsWhenApiKeyNotConfigured(): void
    {
        $controller = new Redis();
        $this->expectException(Exception::class);
        $controller->getKeys([]);
    }

    public function testGetKeyValueThrowsWhenApiKeyNotConfigured(): void
    {
        $controller = new Redis();
        $this->expectException(Exception::class);
        $controller->getKeyValue(['key' => 'test']);
    }

    public function testGetInfoThrowsWhenApiKeyNotConfigured(): void
    {
        $controller = new Redis();
        $this->expectException(Exception::class);
        $controller->getInfo(['key' => 'test']);
    }
}
