<?php

namespace Pressmind\Tests\Integration\DB;

use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for Pressmind\DB\Adapter\Pdo.
 * Exercises edge cases and code paths not covered by the unit tests:
 * meta-query handling in fetchRow, LIMIT replacement, fetchAll with class_name,
 * update/delete without WHERE, noop commit/rollback, and error paths.
 */
class PdoAdapterTest extends AbstractIntegrationTestCase
{
    private static string $tmpTable = '_test_pdo_integ_tmp';

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available (set DB_HOST, DB_NAME, DB_USER, DB_PASS)');
        }
    }

    private function fullTable(): string
    {
        return ($this->db->getTablePrefix() ?? '') . self::$tmpTable;
    }

    private function createTempTable(): void
    {
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS `" . $this->fullTable() . "` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100), val INT) ENGINE=InnoDB"
        );
    }

    private function dropTempTable(): void
    {
        try {
            $this->db->execute("DROP TABLE IF EXISTS `" . $this->fullTable() . "`");
        } catch (\Throwable $e) {
        }
    }

    public function testGetTablePrefix(): void
    {
        $prefix = $this->db->getTablePrefix();
        $this->assertTrue($prefix === null || is_string($prefix));
    }

    public function testInsertAndFetchAll(): void
    {
        $this->dropTempTable();
        $this->createTempTable();
        try {
            $id = $this->db->insert(self::$tmpTable, ['name' => 'a', 'val' => 1], false);
            $this->assertGreaterThan(0, $id);
            $rows = $this->db->fetchAll('SELECT * FROM ' . $this->fullTable() . ' WHERE id = ?', [$id]);
            $this->assertCount(1, $rows);
            $this->assertSame('a', $rows[0]->name);
            $this->assertSame(1, (int) $rows[0]->val);
        } finally {
            $this->dropTempTable();
        }
    }

    public function testReplace(): void
    {
        $this->dropTempTable();
        $this->createTempTable();
        try {
            $id = $this->db->replace(self::$tmpTable, ['id' => 1, 'name' => 'replaced', 'val' => 42]);
            $this->assertGreaterThanOrEqual(1, $id);
            $row = $this->db->fetchRow('SELECT * FROM ' . $this->fullTable() . ' WHERE id = 1');
            $this->assertNotNull($row);
            $this->assertSame('replaced', $row->name);
        } finally {
            $this->dropTempTable();
        }
    }

    public function testUpdateWithWhereClause(): void
    {
        $this->dropTempTable();
        $this->createTempTable();
        try {
            $this->db->insert(self::$tmpTable, ['name' => 'x', 'val' => 10], false);
            $this->db->update(self::$tmpTable, ['name' => 'updated', 'val' => 20], ['id = ?', 1]);
            $this->assertGreaterThanOrEqual(0, $this->db->getAffectedRows());
            $row = $this->db->fetchRow('SELECT * FROM ' . $this->fullTable() . ' WHERE id = 1');
            $this->assertSame('updated', $row->name);
        } finally {
            $this->dropTempTable();
        }
    }

    public function testFetchRowAndFetchOne(): void
    {
        $this->dropTempTable();
        $this->createTempTable();
        try {
            $this->db->insert(self::$tmpTable, ['name' => 'one', 'val' => 1], false);
            $row = $this->db->fetchRow('SELECT id, name FROM ' . $this->fullTable());
            $this->assertNotNull($row);
            $one = $this->db->fetchOne('SELECT name FROM ' . $this->fullTable());
            $this->assertSame('one', $one);
            $null = $this->db->fetchRow('SELECT * FROM ' . $this->fullTable() . ' WHERE id = 99999');
            $this->assertNull($null);
            $this->assertNull($this->db->fetchOne('SELECT name FROM ' . $this->fullTable() . ' WHERE id = 99999'));
        } finally {
            $this->dropTempTable();
        }
    }

    public function testDeleteWithWhereClause(): void
    {
        $this->dropTempTable();
        $this->createTempTable();
        try {
            $this->db->insert(self::$tmpTable, ['name' => 'del', 'val' => 1], false);
            $this->db->delete(self::$tmpTable, ['id = ?', 1]);
            $row = $this->db->fetchRow('SELECT * FROM ' . $this->fullTable());
            $this->assertNull($row);
        } finally {
            $this->dropTempTable();
        }
    }

    public function testBatchInsert(): void
    {
        $this->dropTempTable();
        $this->createTempTable();
        try {
            $count = $this->db->batchInsert(self::$tmpTable, ['name', 'val'], [
                ['a', 1],
                ['b', 2],
            ], false);
            $this->assertSame(2, $count);
            $rows = $this->db->fetchAll('SELECT * FROM ' . $this->fullTable() . ' ORDER BY id');
            $this->assertCount(2, $rows);
        } finally {
            $this->dropTempTable();
        }
    }

    public function testBatchInsertEmptyReturnsZero(): void
    {
        $this->dropTempTable();
        $this->createTempTable();
        try {
            $count = $this->db->batchInsert(self::$tmpTable, ['name', 'val'], [], false);
            $this->assertSame(0, $count);
        } finally {
            $this->dropTempTable();
        }
    }

    public function testTransactionNestingCommitPath(): void
    {
        $this->dropTempTable();
        $this->createTempTable();
        try {
            $this->assertFalse($this->db->inTransaction());
            $this->db->beginTransaction();
            $this->assertTrue($this->db->inTransaction());
            $this->db->beginTransaction();
            $this->db->insert(self::$tmpTable, ['name' => 'tx', 'val' => 1], false);
            $this->db->commit();
            $this->db->commit();
            $this->assertFalse($this->db->inTransaction());
            $row = $this->db->fetchRow('SELECT * FROM ' . $this->fullTable() . ' WHERE name = ?', ['tx']);
            $this->assertNotNull($row);
        } finally {
            $this->dropTempTable();
        }
    }

    public function testRollbackDiscardsChanges(): void
    {
        $this->dropTempTable();
        $this->createTempTable();
        try {
            $this->db->beginTransaction();
            $this->db->insert(self::$tmpTable, ['name' => 'rollback', 'val' => 1], false);
            $this->db->rollback();
            $row = $this->db->fetchRow('SELECT * FROM ' . $this->fullTable() . ' WHERE name = ?', ['rollback']);
            $this->assertNull($row);
        } finally {
            $this->dropTempTable();
        }
    }

    public function testExecuteWithInvalidSqlThrowsException(): void
    {
        $this->expectException(\Throwable::class);
        $this->db->execute('SELECT * FROM _nonexistent_table_xyz_98765');
    }

    public function testFetchRowWithShowTablesMetaQuery(): void
    {
        $this->dropTempTable();
        $this->createTempTable();
        try {
            $row = $this->db->fetchRow("SHOW TABLES LIKE '" . $this->fullTable() . "'");
            $this->assertNotNull($row);
        } finally {
            $this->dropTempTable();
        }
    }

    public function testFetchRowWithDescribeMetaQuery(): void
    {
        $this->dropTempTable();
        $this->createTempTable();
        try {
            $row = $this->db->fetchRow('DESCRIBE ' . $this->fullTable());
            $this->assertNotNull($row);
            $this->assertTrue(property_exists($row, 'Field'));
        } finally {
            $this->dropTempTable();
        }
    }

    public function testFetchRowReplacesExistingLimitClause(): void
    {
        $this->dropTempTable();
        $this->createTempTable();
        try {
            $this->db->insert(self::$tmpTable, ['name' => 'row1', 'val' => 1], false);
            $this->db->insert(self::$tmpTable, ['name' => 'row2', 'val' => 2], false);
            $this->db->insert(self::$tmpTable, ['name' => 'row3', 'val' => 3], false);
            $row = $this->db->fetchRow(
                'SELECT * FROM ' . $this->fullTable() . ' ORDER BY id LIMIT 0,10'
            );
            $this->assertNotNull($row);
            $this->assertSame('row1', $row->name);
        } finally {
            $this->dropTempTable();
        }
    }

    public function testFetchAllWithClassName(): void
    {
        $this->dropTempTable();
        $this->createTempTable();
        try {
            $this->db->insert(self::$tmpTable, ['name' => 'classed', 'val' => 42], false);
            /** @var \Pressmind\DB\Adapter\Pdo $pdo */
            $pdo = $this->db;
            $rows = $pdo->fetchAll(
                'SELECT * FROM ' . $this->fullTable(),
                null,
                \stdClass::class
            );
            $this->assertCount(1, $rows);
            $this->assertInstanceOf(\stdClass::class, $rows[0]);
            $this->assertSame('classed', $rows[0]->name);
        } finally {
            $this->dropTempTable();
        }
    }

    public function testUpdateWithoutWhereAffectsAllRows(): void
    {
        $this->dropTempTable();
        $this->createTempTable();
        try {
            $this->db->insert(self::$tmpTable, ['name' => 'a', 'val' => 1], false);
            $this->db->insert(self::$tmpTable, ['name' => 'b', 'val' => 2], false);
            $this->db->update(self::$tmpTable, ['val' => 99]);
            $rows = $this->db->fetchAll('SELECT * FROM ' . $this->fullTable());
            foreach ($rows as $row) {
                $this->assertSame(99, (int) $row->val);
            }
        } finally {
            $this->dropTempTable();
        }
    }

    public function testDeleteWithoutWhereRemovesAllRows(): void
    {
        $this->dropTempTable();
        $this->createTempTable();
        try {
            $this->db->insert(self::$tmpTable, ['name' => 'a', 'val' => 1], false);
            $this->db->insert(self::$tmpTable, ['name' => 'b', 'val' => 2], false);
            $this->db->delete(self::$tmpTable);
            $rows = $this->db->fetchAll('SELECT * FROM ' . $this->fullTable());
            $this->assertCount(0, $rows);
        } finally {
            $this->dropTempTable();
        }
    }

    public function testGetAffectedRowsReturnsCorrectCount(): void
    {
        $this->dropTempTable();
        $this->createTempTable();
        try {
            $this->db->insert(self::$tmpTable, ['name' => 'a', 'val' => 1], false);
            $this->db->insert(self::$tmpTable, ['name' => 'b', 'val' => 2], false);
            $this->db->insert(self::$tmpTable, ['name' => 'c', 'val' => 3], false);
            $this->db->delete(self::$tmpTable);
            $this->assertSame(3, $this->db->getAffectedRows());
        } finally {
            $this->dropTempTable();
        }
    }

    public function testCommitWithoutTransactionIsNoop(): void
    {
        $this->db->commit();
        $this->assertFalse($this->db->inTransaction());
    }

    public function testRollbackWithoutTransactionIsNoop(): void
    {
        $this->db->rollback();
        $this->assertFalse($this->db->inTransaction());
    }

    public function testBatchInsertWithReplaceInto(): void
    {
        $this->dropTempTable();
        $this->createTempTable();
        try {
            $this->db->batchInsert(self::$tmpTable, ['name', 'val'], [
                ['x', 10],
                ['y', 20],
            ], false);
            $count = $this->db->batchInsert(self::$tmpTable, ['id', 'name', 'val'], [
                [1, 'replaced_x', 100],
            ], true);
            $this->assertSame(1, $count);
            $row = $this->db->fetchRow('SELECT * FROM ' . $this->fullTable() . ' WHERE id = 1');
            $this->assertNotNull($row);
            $this->assertSame('replaced_x', $row->name);
        } finally {
            $this->dropTempTable();
        }
    }

    public function testPrepareReturnsPDOStatement(): void
    {
        /** @var \Pressmind\DB\Adapter\Pdo $pdo */
        $pdo = $this->db;
        $stmt = $pdo->prepare('SELECT 1');
        $this->assertInstanceOf(\PDOStatement::class, $stmt);
    }

    public function testFetchOneReturnsNullForEmptyResult(): void
    {
        $this->dropTempTable();
        $this->createTempTable();
        try {
            $result = $this->db->fetchOne('SELECT name FROM ' . $this->fullTable() . ' WHERE id = 99999');
            $this->assertNull($result);
        } finally {
            $this->dropTempTable();
        }
    }

    public function testNestedTransactionRollbackDiscardsAllChanges(): void
    {
        $this->dropTempTable();
        $this->createTempTable();
        try {
            $this->db->beginTransaction();
            $this->db->beginTransaction();
            $this->db->insert(self::$tmpTable, ['name' => 'nested', 'val' => 1], false);
            $this->db->rollback();
            $this->assertFalse($this->db->inTransaction());
            $row = $this->db->fetchRow('SELECT * FROM ' . $this->fullTable() . ' WHERE name = ?', ['nested']);
            $this->assertNull($row);
        } finally {
            $this->dropTempTable();
        }
    }

    public function testTruncateClearsAllRows(): void
    {
        $this->dropTempTable();
        $this->createTempTable();
        try {
            $this->db->insert(self::$tmpTable, ['name' => 'a', 'val' => 1], false);
            $this->db->insert(self::$tmpTable, ['name' => 'b', 'val' => 2], false);
            $this->db->truncate(self::$tmpTable);
            $rows = $this->db->fetchAll('SELECT * FROM ' . $this->fullTable());
            $this->assertCount(0, $rows);
        } finally {
            $this->dropTempTable();
        }
    }

    public function testFetchRowReturnsNullForEmptyTable(): void
    {
        $this->dropTempTable();
        $this->createTempTable();
        try {
            $row = $this->db->fetchRow('SELECT * FROM ' . $this->fullTable());
            $this->assertNull($row);
        } finally {
            $this->dropTempTable();
        }
    }
}
