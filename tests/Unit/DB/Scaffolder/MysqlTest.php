<?php

namespace Pressmind\Tests\Unit\DB\Scaffolder;

use Exception;
use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\DB\Scaffolder\Mysql;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Pressmind\DB\Scaffolder\Mysql.
 * Uses mocked adapter and ORM object; no real DB or schema changes.
 */
class MysqlTest extends AbstractTestCase
{
    /**
     * Build a mock ORM object with given property definitions and table name.
     */
    private function createOrmMock(string $tableName, string $primaryKey, array $propertyDefinitions, array $indexes = [], ?string $storageEngine = null, bool $dontUseAutoIncrement = false): AbstractObject
    {
        $prefix = 'pmt2core_';
        $orm = $this->getMockBuilder(AbstractObject::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orm->method('getDbTableName')->willReturn($prefix . $tableName);
        $orm->method('getDbPrimaryKey')->willReturn($primaryKey);
        $orm->method('getPropertyDefinitions')->willReturn($propertyDefinitions);
        $orm->method('getDbTableIndexes')->willReturn($indexes);
        $orm->method('getStorageDefinition')->willReturnCallback(function ($key) use ($storageEngine) {
            return $key === 'storage_engine' ? $storageEngine : null;
        });
        $orm->method('dontUseAutoIncrementOnPrimaryKey')->willReturn($dontUseAutoIncrement);
        return $orm;
    }

    public function testGetLogInitiallyEmpty(): void
    {
        $definitions = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
        ];
        $orm = $this->createOrmMock('test_table', 'id', $definitions);
        $scaffolder = new Mysql($orm);
        $this->assertSame([], $scaffolder->getLog());
    }

    public function testRunWithoutDropRecordsCreateInLog(): void
    {
        $definitions = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'name' => ['type' => 'varchar', 'required' => false, 'name' => 'name', 'validators' => [['name' => 'maxlength', 'params' => 255]]],
        ];
        $orm = $this->createOrmMock('scaffold_test', 'id', $definitions);
        $executed = [];
        $db = $this->createMock(AdapterInterface::class);
        $db->method('execute')->willReturnCallback(function ($sql) use (&$executed) {
            $executed[] = $sql;
        });
        $db->method('fetchAll')->willReturn([]);
        Registry::getInstance()->add('db', $db);

        $scaffolder = new Mysql($orm);
        $scaffolder->run(false);

        $log = $scaffolder->getLog();
        $this->assertCount(1, $log);
        $this->assertStringContainsString('created', $log[0]);
        $this->assertStringContainsString('scaffold_test', $log[0]);
        $this->assertNotEmpty($executed);
        $createSql = $executed[0];
        $this->assertStringContainsString('CREATE TABLE', $createSql);
        $this->assertStringContainsString('pmt2core_scaffold_test', $createSql);
        $this->assertStringContainsString('PRIMARY KEY', $createSql);
    }

    public function testRunWithDropCallsExecuteWithDropFirst(): void
    {
        $definitions = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
        ];
        $orm = $this->createOrmMock('drop_test', 'id', $definitions);
        $executed = [];
        $db = $this->createMock(AdapterInterface::class);
        $db->method('execute')->willReturnCallback(function ($sql) use (&$executed) {
            $executed[] = $sql;
        });
        $db->method('fetchAll')->willReturn([]);
        Registry::getInstance()->add('db', $db);

        $scaffolder = new Mysql($orm);
        $scaffolder->run(true);

        $this->assertStringContainsString('DROP TABLE', $executed[0]);
        $this->assertStringContainsString('drop_test', $executed[0]);
        $log = $scaffolder->getLog();
        $this->assertStringContainsString('dropped', $log[0]);
    }

    public function testRunWithStorageEngineInSql(): void
    {
        $definitions = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
        ];
        $orm = $this->createOrmMock('engine_test', 'id', $definitions, [], 'myisam');
        $executed = [];
        $db = $this->createMock(AdapterInterface::class);
        $db->method('execute')->willReturnCallback(function ($sql) use (&$executed) {
            $executed[] = $sql;
        });
        $db->method('fetchAll')->willReturn([]);
        Registry::getInstance()->add('db', $db);

        $scaffolder = new Mysql($orm);
        $scaffolder->run(false);

        $this->assertStringContainsString('ENGINE=myisam', $executed[0]);
    }

    public function testRunWithEnumFieldInSql(): void
    {
        $definitions = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'status' => ['type' => 'string', 'required' => true, 'name' => 'status', 'validators' => [['name' => 'inarray', 'params' => ['active', 'inactive', 'pending']]]],
        ];
        $orm = $this->createOrmMock('enum_test', 'id', $definitions);
        $executed = [];
        $db = $this->createMock(AdapterInterface::class);
        $db->method('execute')->willReturnCallback(function ($sql) use (&$executed) {
            $executed[] = $sql;
        });
        $db->method('fetchAll')->willReturn([]);
        Registry::getInstance()->add('db', $db);

        $scaffolder = new Mysql($orm);
        $scaffolder->run(false);

        $this->assertStringContainsString("ENUM('active','inactive','pending')", $executed[0]);
    }

    public function testRunWithEncryptedFieldBecomesBlobInSql(): void
    {
        $definitions = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'secret' => ['type' => 'varchar', 'required' => false, 'name' => 'secret', 'encrypt' => true, 'unique' => true, 'validators' => [['name' => 'maxlength', 'params' => 255]]],
        ];
        $orm = $this->createOrmMock('encrypt_test', 'id', $definitions);
        $executed = [];
        $db = $this->createMock(AdapterInterface::class);
        $db->method('execute')->willReturnCallback(function ($sql) use (&$executed) {
            $executed[] = $sql;
        });
        $db->method('fetchAll')->willReturn([]);
        Registry::getInstance()->add('db', $db);

        $scaffolder = new Mysql($orm);
        $scaffolder->run(false);

        $this->assertStringContainsString('BLOB', $executed[0]);
        $this->assertStringNotContainsString('UNIQUE KEY secret', $executed[0]);
    }

    public function testRunWithBooleanDefaultValueInSql(): void
    {
        $definitions = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'active' => ['type' => 'boolean', 'required' => true, 'name' => 'active', 'default_value' => true],
        ];
        $orm = $this->createOrmMock('bool_default_test', 'id', $definitions);
        $executed = [];
        $db = $this->createMock(AdapterInterface::class);
        $db->method('execute')->willReturnCallback(function ($sql) use (&$executed) {
            $executed[] = $sql;
        });
        $db->method('fetchAll')->willReturn([]);
        Registry::getInstance()->add('db', $db);

        $scaffolder = new Mysql($orm);
        $scaffolder->run(false);

        $this->assertStringContainsString("DEFAULT '1'", $executed[0]);
    }

    public function testRunWithDontUseAutoIncrementOnPrimaryKey(): void
    {
        $definitions = [
            'id' => ['type' => 'varchar', 'required' => true, 'name' => 'id', 'validators' => [['name' => 'maxlength', 'params' => 36]]],
        ];
        $orm = $this->createOrmMock('no_auto_test', 'id', $definitions, [], null, true);
        $executed = [];
        $db = $this->createMock(AdapterInterface::class);
        $db->method('execute')->willReturnCallback(function ($sql) use (&$executed) {
            $executed[] = $sql;
        });
        $db->method('fetchAll')->willReturn([]);
        Registry::getInstance()->add('db', $db);

        $scaffolder = new Mysql($orm);
        $scaffolder->run(false);

        $this->assertStringNotContainsString('AUTO_INCREMENT', $executed[0]);
    }

    public function testRunSkipsRelationAndComputedTypes(): void
    {
        $definitions = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'link' => ['type' => 'relation', 'required' => false, 'name' => 'link'],
            'computed_val' => ['type' => 'computed', 'required' => false, 'name' => 'computed_val'],
            'title' => ['type' => 'text', 'required' => false, 'name' => 'title'],
        ];
        $orm = $this->createOrmMock('skip_types_test', 'id', $definitions);
        $executed = [];
        $db = $this->createMock(AdapterInterface::class);
        $db->method('execute')->willReturnCallback(function ($sql) use (&$executed) {
            $executed[] = $sql;
        });
        $db->method('fetchAll')->willReturn([]);
        Registry::getInstance()->add('db', $db);

        $scaffolder = new Mysql($orm);
        $scaffolder->run(false);

        $createSql = $executed[0];
        $this->assertStringContainsString('`id`', $createSql);
        $this->assertStringContainsString('`title`', $createSql);
        $this->assertStringNotContainsString('`link`', $createSql);
        $this->assertStringNotContainsString('`computed_val`', $createSql);
    }

    public function testRunWithIndexesGeneratesCreateIndexStatements(): void
    {
        $definitions = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'name' => ['type' => 'varchar', 'required' => false, 'name' => 'name', 'validators' => [['name' => 'maxlength', 'params' => 100]], 'index' => ['idx_name' => 'index']],
        ];
        $orm = $this->createOrmMock('index_test', 'id', $definitions);
        $executed = [];
        $db = $this->createMock(AdapterInterface::class);
        $db->method('execute')->willReturnCallback(function ($sql) use (&$executed) {
            $executed[] = $sql;
        });
        $db->method('fetchAll')->willReturnCallback(function ($sql) {
            if (stripos($sql, 'SHOW INDEX') !== false) {
                return [];
            }
            return [];
        });
        Registry::getInstance()->add('db', $db);

        $scaffolder = new Mysql($orm);
        $scaffolder->run(false);

        $indexSqlFound = false;
        foreach ($executed as $sql) {
            if (strpos($sql, 'CREATE INDEX idx_name') !== false) {
                $indexSqlFound = true;
                break;
            }
        }
        $this->assertTrue($indexSqlFound, 'Expected CREATE INDEX statement for idx_name');
    }

    public function testRunWithUniqueFieldAddsUniqueKeyInSql(): void
    {
        $definitions = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'code' => ['type' => 'varchar', 'required' => true, 'name' => 'code', 'unique' => true, 'validators' => [['name' => 'maxlength', 'params' => 50]]],
        ];
        $orm = $this->createOrmMock('unique_test', 'id', $definitions);
        $executed = [];
        $db = $this->createMock(AdapterInterface::class);
        $db->method('execute')->willReturnCallback(function ($sql) use (&$executed) {
            $executed[] = $sql;
        });
        $db->method('fetchAll')->willReturn([]);
        Registry::getInstance()->add('db', $db);

        $scaffolder = new Mysql($orm);
        $scaffolder->run(false);

        $this->assertStringContainsString('UNIQUE KEY code', $executed[0]);
    }

    public function testRunWithFulltextIndexGeneratesFulltextStatement(): void
    {
        $definitions = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'content' => ['type' => 'text', 'required' => false, 'name' => 'content', 'index' => ['ft_content' => 'fulltext']],
        ];
        $orm = $this->createOrmMock('fulltext_test', 'id', $definitions, [], 'myisam');
        $executed = [];
        $db = $this->createMock(AdapterInterface::class);
        $db->method('execute')->willReturnCallback(function ($sql) use (&$executed) {
            $executed[] = $sql;
        });
        $db->method('fetchAll')->willReturnCallback(function ($sql) {
            if (stripos($sql, 'SHOW INDEX') !== false) {
                return [];
            }
            return [];
        });
        Registry::getInstance()->add('db', $db);

        $scaffolder = new Mysql($orm);
        $scaffolder->run(false);

        $fulltextFound = false;
        foreach ($executed as $sql) {
            if (strpos($sql, 'CREATE FULLTEXT INDEX ft_content') !== false) {
                $fulltextFound = true;
                break;
            }
        }
        $this->assertTrue($fulltextFound, 'Expected CREATE FULLTEXT INDEX statement');
    }
}
