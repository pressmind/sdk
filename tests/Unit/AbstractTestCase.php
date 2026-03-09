<?php

namespace Pressmind\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\Registry;

/**
 * Base test case for unit tests. Resets Registry and provides mock config and DB adapter
 * so ORM and other components can be instantiated without a real database.
 */
abstract class AbstractTestCase extends TestCase
{
    /**
     * @var array Default config for tests (cache disabled, no customer data).
     */
    protected $defaultConfig = [
        'cache' => [
            'enabled' => false,
            'types' => [],
        ],
        'database' => [
            'dbname' => 'test',
        ],
        'logging' => [
            'enable_advanced_object_log' => false,
            'mode' => 'ALL',
            'storage' => 'filesystem',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Registry::clear();
        $config = $this->createMockConfig([]);
        $db = $this->createMockDb();
        $registry = Registry::getInstance();
        $registry->add('config', $config);
        $registry->add('db', $db);
    }

    protected function tearDown(): void
    {
        Registry::clear();
        parent::tearDown();
    }

    /**
     * Build config array for tests. Override keys via $overrides.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function createMockConfig(array $overrides): array
    {
        return array_replace_recursive($this->defaultConfig, $overrides);
    }

    /**
     * Create a mock DB adapter that returns safe defaults (no real DB access).
     */
    protected function createMockDb(): AdapterInterface
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('fetchAll')->willReturn([]);
        $adapter->method('fetchRow')->willReturn(null);
        $adapter->method('fetchOne')->willReturn(null);
        $adapter->method('getAffectedRows')->willReturn(0);
        $adapter->method('getTablePrefix')->willReturn('pmt2core_');
        $adapter->method('inTransaction')->willReturn(false);
        $adapter->method('execute')->willReturn(null);
        $adapter->method('insert')->willReturn(null);
        $adapter->method('replace')->willReturn(null);
        $adapter->method('update')->willReturn(null);
        $adapter->method('delete')->willReturn(null);
        $adapter->method('truncate')->willReturn(null);
        $adapter->method('batchInsert')->willReturn(1);
        $adapter->method('beginTransaction')->willReturn(null);
        $adapter->method('commit')->willReturn(null);
        $adapter->method('rollback')->willReturn(null);
        return $adapter;
    }
}
