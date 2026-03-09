<?php

namespace Pressmind\Tests\Unit\DB\Config;

use PHPUnit\Framework\TestCase;
use Pressmind\DB\Config\Pdo;

/**
 * Unit tests for Pressmind\DB\Config\Pdo singleton factory.
 */
class PdoConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetSingleton();
    }

    protected function tearDown(): void
    {
        $this->resetSingleton();
        parent::tearDown();
    }

    private function resetSingleton(): void
    {
        $ref = new \ReflectionClass(Pdo::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    public function testCreateReturnsPdoInstance(): void
    {
        $config = Pdo::create('localhost', 'testdb', 'user', 'pass');
        $this->assertInstanceOf(Pdo::class, $config);
    }

    public function testCreateSetsAllProperties(): void
    {
        $config = Pdo::create('db.host', 'mydb', 'admin', 'secret');
        $this->assertSame('db.host', $config->host);
        $this->assertSame('mydb', $config->dbname);
        $this->assertSame('admin', $config->username);
        $this->assertSame('secret', $config->password);
    }

    public function testCreateDefaultPort(): void
    {
        $config = Pdo::create('localhost', 'testdb', 'user', 'pass');
        $this->assertSame('3306', $config->port);
    }

    public function testCreateWithCustomPort(): void
    {
        $config = Pdo::create('localhost', 'testdb', 'user', 'pass', '3307');
        $this->assertSame('3307', $config->port);
    }

    public function testCreateWithTablePrefix(): void
    {
        $config = Pdo::create('localhost', 'testdb', 'user', 'pass', '3306', 'pmt2core_');
        $this->assertSame('pmt2core_', $config->table_prefix);
    }

    public function testCreateReturnsSingletonInstance(): void
    {
        $first = Pdo::create('host1', 'db1', 'u1', 'p1');
        $second = Pdo::create('host2', 'db2', 'u2', 'p2');
        $this->assertSame($first, $second);
        $this->assertSame('host1', $second->host);
    }
}
