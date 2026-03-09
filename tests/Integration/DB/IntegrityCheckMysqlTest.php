<?php

namespace Pressmind\Tests\Integration\DB;

use Pressmind\DB\IntegrityCheck\Mysql;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for Pressmind\DB\IntegrityCheck\Mysql.
 * Creates real tables, then checks ORM-vs-DB differences: missing tables,
 * column type/null mismatches, index gaps, engine mismatches, auto_increment settings.
 */
class IntegrityCheckMysqlTest extends AbstractIntegrationTestCase
{
    /** @var string[] */
    private array $tablesToCleanup = [];

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available (set DB_HOST, DB_NAME, DB_USER, DB_PASS)');
        }
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            foreach ($this->tablesToCleanup as $table) {
                try {
                    $this->db->execute("DROP TABLE IF EXISTS `{$table}`");
                } catch (\Throwable $e) {
                }
            }
        }
        parent::tearDown();
    }

    private function trackTable(string $name): void
    {
        $this->tablesToCleanup[] = $name;
    }

    private function createOrmMock(
        string $tableName,
        string $primaryKey,
        array $properties,
        ?string $engine = null,
        ?array $globalIndexes = null,
        bool $dontAutoIncrement = false
    ): AbstractObject {
        $orm = $this->getMockBuilder(AbstractObject::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orm->method('getDbTableName')->willReturn($tableName);
        $orm->method('getDbPrimaryKey')->willReturn($primaryKey);
        $orm->method('getPropertyDefinitions')->willReturn($properties);
        $orm->method('getDbTableIndexes')->willReturn([]);
        $orm->method('hasProperty')->willReturnCallback(function ($name) use ($properties) {
            return isset($properties[$name]);
        });
        $orm->method('getStorageDefinition')->willReturnCallback(function ($key) use ($engine, $globalIndexes, $primaryKey) {
            if ($key === 'storage_engine') {
                return $engine;
            }
            if ($key === 'indexes') {
                return $globalIndexes;
            }
            if ($key === 'primary_key') {
                return $primaryKey;
            }
            return null;
        });
        $orm->method('dontUseAutoincrementOnPrimaryKey')->willReturn($dontAutoIncrement);
        return $orm;
    }

    private function findDifference(array $differences, string $action): ?array
    {
        foreach ($differences as $diff) {
            if ($diff['action'] === $action) {
                return $diff;
            }
        }
        return null;
    }

    private function filterDifferences(array $differences, string $action): array
    {
        return array_values(array_filter($differences, fn($d) => $d['action'] === $action));
    }

    public function testCheckReportsCreateTableWhenTableMissing(): void
    {
        $properties = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
        ];
        $orm = $this->createOrmMock('_test_ic_nonexistent_xyz', 'id', $properties);
        $checker = new Mysql($orm);
        $result = $checker->check();

        $this->assertIsArray($result);
        $diff = $this->findDifference($result, 'create_table');
        $this->assertNotNull($diff);
        $this->assertSame('_test_ic_nonexistent_xyz', $diff['table']);
    }

    public function testCheckReturnsTrueWhenTableMatchesORM(): void
    {
        $table = '_test_ic_match';
        $this->trackTable($table);
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS `{$table}` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NULL) ENGINE=InnoDB"
        );

        $properties = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'name' => ['type' => 'string', 'required' => false, 'name' => 'name', 'validators' => [['name' => 'maxlength', 'params' => 50]]],
        ];
        $orm = $this->createOrmMock($table, 'id', $properties);
        $checker = new Mysql($orm);
        $result = $checker->check();

        $this->assertTrue($result);
    }

    public function testCheckReportsDropColumnForExtraDBColumn(): void
    {
        $table = '_test_ic_dropcol';
        $this->trackTable($table);
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS `{$table}` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NULL, extra_col INT NULL) ENGINE=InnoDB"
        );

        $properties = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'name' => ['type' => 'string', 'required' => false, 'name' => 'name', 'validators' => [['name' => 'maxlength', 'params' => 50]]],
        ];
        $orm = $this->createOrmMock($table, 'id', $properties);
        $checker = new Mysql($orm);
        $result = $checker->check();

        $this->assertIsArray($result);
        $diff = $this->findDifference($result, 'drop_column');
        $this->assertNotNull($diff);
        $this->assertSame('extra_col', $diff['column_name']);
    }

    public function testCheckReportsCreateColumnForMissingDBColumn(): void
    {
        $table = '_test_ic_createcol';
        $this->trackTable($table);
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS `{$table}` (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB"
        );

        $properties = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'title' => ['type' => 'string', 'required' => false, 'name' => 'title', 'validators' => [['name' => 'maxlength', 'params' => 200]]],
        ];
        $orm = $this->createOrmMock($table, 'id', $properties);
        $checker = new Mysql($orm);
        $result = $checker->check();

        $this->assertIsArray($result);
        $diff = $this->findDifference($result, 'create_column');
        $this->assertNotNull($diff);
        $this->assertSame('title', $diff['column_name']);
    }

    public function testCheckReportsAlterColumnTypeForTypeMismatch(): void
    {
        $table = '_test_ic_coltype';
        $this->trackTable($table);
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS `{$table}` (id INT AUTO_INCREMENT PRIMARY KEY, val INT NULL) ENGINE=InnoDB"
        );

        $properties = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'val' => ['type' => 'string', 'required' => false, 'name' => 'val', 'validators' => [['name' => 'maxlength', 'params' => 100]]],
        ];
        $orm = $this->createOrmMock($table, 'id', $properties);
        $checker = new Mysql($orm);
        $result = $checker->check();

        $this->assertIsArray($result);
        $diff = $this->findDifference($result, 'alter_column_type');
        $this->assertNotNull($diff);
        $this->assertSame('val', $diff['column_name']);
        $this->assertSame('varchar(100)', $diff['column_type']);
    }

    public function testCheckReportsAlterColumnNullForNullMismatch(): void
    {
        $table = '_test_ic_colnull';
        $this->trackTable($table);
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS `{$table}` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NOT NULL DEFAULT '') ENGINE=InnoDB"
        );

        $properties = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'name' => ['type' => 'string', 'required' => false, 'name' => 'name', 'validators' => [['name' => 'maxlength', 'params' => 50]]],
        ];
        $orm = $this->createOrmMock($table, 'id', $properties);
        $checker = new Mysql($orm);
        $result = $checker->check();

        $this->assertIsArray($result);
        $diff = $this->findDifference($result, 'alter_column_null');
        $this->assertNotNull($diff);
        $this->assertSame('name', $diff['column_name']);
        $this->assertSame('NULL', $diff['column_null']);
    }

    public function testCheckReportsAlterEngineForEngineMismatch(): void
    {
        $table = '_test_ic_engine';
        $this->trackTable($table);
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS `{$table}` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NULL) ENGINE=InnoDB"
        );

        $properties = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'name' => ['type' => 'string', 'required' => false, 'name' => 'name', 'validators' => [['name' => 'maxlength', 'params' => 50]]],
        ];
        $orm = $this->createOrmMock($table, 'id', $properties, 'myisam');
        $checker = new Mysql($orm);
        $result = $checker->check();

        $this->assertIsArray($result);
        $diff = $this->findDifference($result, 'alter_engine');
        $this->assertNotNull($diff);
        $this->assertSame('myisam', $diff['engine']);
    }

    public function testCheckNoEngineIssueWhenEngineMatches(): void
    {
        $table = '_test_ic_engine_ok';
        $this->trackTable($table);
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS `{$table}` (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB"
        );

        $properties = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
        ];
        $orm = $this->createOrmMock($table, 'id', $properties, 'innodb');
        $checker = new Mysql($orm);
        $result = $checker->check();

        $this->assertTrue($result);
    }

    public function testCheckReportsAddIndexForMissingIndex(): void
    {
        $table = '_test_ic_addidx';
        $this->trackTable($table);
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS `{$table}` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NULL) ENGINE=InnoDB"
        );

        $properties = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'name' => ['type' => 'varchar', 'required' => false, 'name' => 'name', 'validators' => [['name' => 'maxlength', 'params' => 100]], 'index' => ['idx_name' => 'index']],
        ];
        $orm = $this->createOrmMock($table, 'id', $properties);
        $checker = new Mysql($orm);
        $result = $checker->check();

        $this->assertIsArray($result);
        $diff = $this->findDifference($result, 'add_index');
        $this->assertNotNull($diff);
        $this->assertSame('idx_name', $diff['index_name']);
    }

    public function testCheckReportsDropIndexForExtraDBIndex(): void
    {
        $table = '_test_ic_dropidx';
        $this->trackTable($table);
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS `{$table}` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NULL) ENGINE=InnoDB"
        );
        $this->db->execute("CREATE INDEX idx_extra ON `{$table}` (name)");

        $properties = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'name' => ['type' => 'string', 'required' => false, 'name' => 'name', 'validators' => [['name' => 'maxlength', 'params' => 100]]],
        ];
        $orm = $this->createOrmMock($table, 'id', $properties);
        $checker = new Mysql($orm);
        $result = $checker->check();

        $this->assertIsArray($result);
        $diff = $this->findDifference($result, 'drop_index');
        $this->assertNotNull($diff);
        $this->assertSame('idx_extra', $diff['index_name']);
    }

    public function testCheckReportsAddIndexForMissingGlobalIndex(): void
    {
        $table = '_test_ic_globalidx';
        $this->trackTable($table);
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS `{$table}` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NULL, code VARCHAR(50) NULL) ENGINE=InnoDB"
        );

        $properties = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'name' => ['type' => 'string', 'required' => false, 'name' => 'name', 'validators' => [['name' => 'maxlength', 'params' => 100]]],
            'code' => ['type' => 'string', 'required' => false, 'name' => 'code', 'validators' => [['name' => 'maxlength', 'params' => 50]]],
        ];
        $globalIndexes = [
            'idx_name_code' => ['type' => 'index', 'columns' => ['name', 'code']],
        ];
        $orm = $this->createOrmMock($table, 'id', $properties, null, $globalIndexes);
        $checker = new Mysql($orm);
        $result = $checker->check();

        $this->assertIsArray($result);
        $addIndexDiffs = $this->filterDifferences($result, 'add_index');
        $found = false;
        foreach ($addIndexDiffs as $diff) {
            if ($diff['index_name'] === 'idx_name_code') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected add_index for global index idx_name_code');
    }

    public function testCheckReportsRemoveAutoIncrementWhenOrmDoesNotWantIt(): void
    {
        $table = '_test_ic_rmauto';
        $this->trackTable($table);
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS `{$table}` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NULL) ENGINE=InnoDB"
        );

        $properties = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'name' => ['type' => 'string', 'required' => false, 'name' => 'name', 'validators' => [['name' => 'maxlength', 'params' => 50]]],
        ];
        $orm = $this->createOrmMock($table, 'id', $properties, null, null, true);
        $checker = new Mysql($orm);
        $result = $checker->check();

        $this->assertIsArray($result);
        $diff = $this->findDifference($result, 'remove_auto_increment');
        $this->assertNotNull($diff, 'Expected remove_auto_increment when ORM says dontUseAutoincrement');
        $this->assertSame('id', $diff['column_name']);
    }

    public function testCheckReportsSetAutoIncrementWhenOrmWantsIt(): void
    {
        $table = '_test_ic_setauto';
        $this->trackTable($table);
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS `{$table}` (id INT NOT NULL, name VARCHAR(50) NULL, PRIMARY KEY (id)) ENGINE=InnoDB"
        );

        $properties = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'name' => ['type' => 'string', 'required' => false, 'name' => 'name', 'validators' => [['name' => 'maxlength', 'params' => 50]]],
        ];
        $orm = $this->createOrmMock($table, 'id', $properties, null, null, false);
        $checker = new Mysql($orm);
        $result = $checker->check();

        $this->assertIsArray($result);
        $diff = $this->findDifference($result, 'set_auto_increment');
        $this->assertNotNull($diff, 'Expected set_auto_increment when ORM wants autoincrement but DB lacks it');
        $this->assertSame('id', $diff['column_name']);
    }

    public function testCheckReportsAlterPrimaryKeyForMismatch(): void
    {
        $table = '_test_ic_altpk';
        $this->trackTable($table);
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS `{$table}` (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NULL) ENGINE=InnoDB"
        );

        $properties = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'name' => ['type' => 'string', 'required' => false, 'name' => 'name', 'validators' => [['name' => 'maxlength', 'params' => 50]]],
        ];
        $orm = $this->createOrmMock($table, 'id,name', $properties);
        $checker = new Mysql($orm);
        $result = $checker->check();

        $this->assertIsArray($result);
        $diff = $this->findDifference($result, 'alter_primary_key');
        $this->assertNotNull($diff, 'Expected alter_primary_key when DB and ORM primary keys differ');
        $this->assertSame('id,name', $diff['column_names']);
        $this->assertSame('id', $diff['old_column_names']);
    }
}
