<?php

namespace Pressmind\Tests\Unit\DB;

use PHPUnit\Framework\TestCase;
use Pressmind\DB\Adapter\AdapterInterface;

/**
 * Unit tests for transaction method contract (AdapterInterface).
 * Real Pdo transaction behaviour is tested in integration tests with a database.
 */
class PdoTransactionTest extends TestCase
{
    public function testMockAdapterSupportsTransactions(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('inTransaction')->willReturn(false);
        $this->assertFalse($adapter->inTransaction());

        $adapter->expects($this->once())->method('beginTransaction');
        $adapter->expects($this->once())->method('commit');
        $adapter->expects($this->never())->method('rollback');
        $adapter->beginTransaction();
        $adapter->commit();
    }

    public function testMockAdapterRollback(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->once())->method('beginTransaction');
        $adapter->expects($this->once())->method('rollback');
        $adapter->expects($this->never())->method('commit');
        $adapter->beginTransaction();
        $adapter->rollback();
    }

    public function testMockAdapterBatchInsertSignature(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('batchInsert')->willReturn(2);
        $result = $adapter->batchInsert('test_table', ['id', 'name'], [['1', 'a'], ['2', 'b']], true);
        $this->assertSame(2, $result);
    }
}
