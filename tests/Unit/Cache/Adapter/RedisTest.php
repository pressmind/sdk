<?php

namespace Pressmind\Tests\Unit\Cache\Adapter;

use Pressmind\Cache\Adapter\Redis;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Pressmind\Cache\Adapter\Redis.
 * Skips when Redis extension is not loaded or connection fails (no real I/O to production).
 */
class RedisTest extends AbstractTestCase
{
    /** @var Redis|null */
    private $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not loaded');
        }
        $host = getenv('REDIS_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('REDIS_PORT') ?: 6379);
        $config = $this->createMockConfig([
            'cache' => [
                'adapter' => [
                    'config' => [
                        'host' => $host,
                        'port' => $port,
                        'password' => getenv('REDIS_PASSWORD') ?: '',
                    ],
                ],
                'key_prefix' => 'pm_unit_test_',
                'max_idle_time' => 3600,
                'update_frequency' => 86400,
            ],
        ]);
        Registry::getInstance()->add('config', $config);
        try {
            $this->adapter = new Redis();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis not available: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->adapter !== null) {
            try {
                $server = $this->getRedisServer();
                foreach (['redis_unit_test_key', 'redis_orphan_test_key'] as $key) {
                    $cacheKey = 'pm:pm_unit_test_:' . $key;
                    $server->del($cacheKey);
                    $server->hDel('pm:pm_unit_test_:info', $cacheKey);
                    $server->hDel('pm:pm_unit_test_:time', $cacheKey);
                    $server->hDel('pm:pm_unit_test_:idletime', $cacheKey);
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
        parent::tearDown();
    }

    public function testAddGet(): void
    {
        $key = 'redis_unit_test_key';
        $value = 'unit-test-value-' . uniqid();
        $this->assertTrue($this->adapter->add($key, $value, null, 60));
        $this->assertSame($value, $this->adapter->get($key));
    }

    public function testExists(): void
    {
        $key = 'redis_unit_test_key';
        $this->adapter->add($key, 'v', null, 60);
        $this->assertTrue((bool) $this->adapter->exists($key));
        $this->adapter->remove($key);
        $this->assertFalse((bool) $this->adapter->exists($key));
    }

    public function testRemove(): void
    {
        $key = 'redis_unit_test_key';
        $this->adapter->add($key, 'to-delete', null, 60);
        $this->assertTrue((bool) $this->adapter->exists($key));
        $this->adapter->remove($key);
        $this->assertFalse((bool) $this->adapter->exists($key));
        $this->assertSame(false, $this->adapter->get($key));
    }

    public function testGetMissingKeyReturnsFalse(): void
    {
        $this->assertSame(false, $this->adapter->get('nonexistent_key_xyz'));
    }

    public function testCleanUpIgnoresBookkeepingHashes(): void
    {
        $key = 'redis_unit_test_key';
        $value = 'unit-test-value-' . uniqid();
        $this->assertTrue($this->adapter->add($key, $value, null, 60));

        $cacheKey = 'pm:pm_unit_test_:' . $key;
        $this->getRedisServer()->hSet('pm:pm_unit_test_:idletime', $cacheKey, 0);

        $this->assertSame('Task completed', $this->adapter->cleanUp());
        $this->assertSame($value, $this->adapter->get($key));
    }

    public function testCleanUpSkipsKeyWithoutTimestamp(): void
    {
        $key = 'redis_orphan_test_key';
        $server = $this->getRedisServer();
        $server->set('pm:pm_unit_test_:' . $key, 'orphaned', 60);

        $this->assertSame('Task completed', $this->adapter->cleanUp());
        $this->assertTrue((bool) $this->adapter->exists($key));
    }

    private function getRedisServer(): \Redis
    {
        $property = new \ReflectionProperty(Redis::class, '_server');
        $property->setAccessible(true);
        return $property->getValue($this->adapter);
    }
}
