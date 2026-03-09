<?php

namespace Pressmind\Tests\Integration\DB;

use Pressmind\DB\Scaffolder\Mysql;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for Pressmind\DB\Scaffolder\Mysql.
 * Verifies actual table creation in MySQL with various property types,
 * indexes, unique constraints, encryption, default values, and engine settings.
 */
class ScaffolderMysqlTest extends AbstractIntegrationTestCase
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

    private function createOrmMock(
        string $tableName,
        string $primaryKey,
        array $propertyDefinitions,
        array $indexes = [],
        ?string $storageEngine = null,
        bool $dontAutoIncrement = false
    ): AbstractObject {
        $this->tablesToCleanup[] = $tableName;
        $orm = $this->getMockBuilder(AbstractObject::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orm->method('getDbTableName')->willReturn($tableName);
        $orm->method('getDbPrimaryKey')->willReturn($primaryKey);
        $orm->method('getPropertyDefinitions')->willReturn($propertyDefinitions);
        $orm->method('getDbTableIndexes')->willReturn($indexes);
        $orm->method('getStorageDefinition')->willReturnCallback(function ($key) use ($storageEngine) {
            if ($key === 'storage_engine') {
                return $storageEngine;
            }
            return null;
        });
        $orm->method('dontUseAutoIncrementOnPrimaryKey')->willReturn($dontAutoIncrement);
        return $orm;
    }

    private function tableExists(string $tableName): bool
    {
        return $this->db->fetchRow("SHOW TABLES LIKE '{$tableName}'") !== null;
    }

    private function getColumnInfo(string $table, string $column): ?object
    {
        $columns = $this->db->fetchAll("DESCRIBE `{$table}`");
        foreach ($columns as $col) {
            if ($col->Field === $column) {
                return $col;
            }
        }
        return null;
    }

    public function testCreateTableWithBasicFieldTypes(): void
    {
        $definitions = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'name' => ['type' => 'varchar', 'required' => false, 'name' => 'name', 'validators' => [['name' => 'maxlength', 'params' => 255]]],
            'description' => ['type' => 'text', 'required' => false, 'name' => 'description'],
            'price' => ['type' => 'float', 'required' => false, 'name' => 'price'],
            'active' => ['type' => 'boolean', 'required' => true, 'name' => 'active', 'default_value' => 0],
            'created' => ['type' => 'datetime', 'required' => false, 'name' => 'created'],
        ];
        $orm = $this->createOrmMock('_test_scaff_basic', 'id', $definitions);
        $scaffolder = new Mysql($orm);
        $scaffolder->run(false);

        $this->assertTrue($this->tableExists('_test_scaff_basic'));
        $columns = $this->db->fetchAll('DESCRIBE `_test_scaff_basic`');
        $names = array_map(fn($c) => $c->Field, $columns);
        $this->assertContains('id', $names);
        $this->assertContains('name', $names);
        $this->assertContains('description', $names);
        $this->assertContains('price', $names);
        $this->assertContains('active', $names);
        $this->assertContains('created', $names);
    }

    public function testCreateTableWithUniqueConstraint(): void
    {
        $definitions = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'code' => ['type' => 'varchar', 'required' => true, 'name' => 'code', 'unique' => true, 'validators' => [['name' => 'maxlength', 'params' => 50]]],
        ];
        $orm = $this->createOrmMock('_test_scaff_uniq', 'id', $definitions);
        $scaffolder = new Mysql($orm);
        $scaffolder->run(false);

        $this->assertTrue($this->tableExists('_test_scaff_uniq'));
        $indexes = $this->db->fetchAll("SHOW INDEX FROM `_test_scaff_uniq` WHERE Key_name = 'code'");
        $this->assertNotEmpty($indexes);
        $this->assertEquals(0, (int) $indexes[0]->Non_unique);
    }

    public function testDropAndRecreateTable(): void
    {
        $definitions = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
        ];
        $orm = $this->createOrmMock('_test_scaff_drop', 'id', $definitions);
        $scaffolder = new Mysql($orm);
        $scaffolder->run(false);
        $this->assertTrue($this->tableExists('_test_scaff_drop'));

        $scaffolder2 = new Mysql($orm);
        $scaffolder2->run(true);
        $log = $scaffolder2->getLog();
        $this->assertTrue($this->tableExists('_test_scaff_drop'));
        $this->assertStringContainsString('dropped', $log[0]);
        $this->assertStringContainsString('created', $log[1]);
    }

    public function testCreateTableWithEncryptedFieldBecomesBLOB(): void
    {
        $definitions = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'secret' => ['type' => 'varchar', 'required' => false, 'name' => 'secret', 'encrypt' => true, 'unique' => true, 'validators' => [['name' => 'maxlength', 'params' => 255]]],
        ];
        $orm = $this->createOrmMock('_test_scaff_enc', 'id', $definitions);
        $scaffolder = new Mysql($orm);
        $scaffolder->run(false);

        $col = $this->getColumnInfo('_test_scaff_enc', 'secret');
        $this->assertNotNull($col);
        $this->assertStringContainsString('blob', strtolower($col->Type));
    }

    public function testCreateTableWithDefaultValue(): void
    {
        $definitions = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'status' => ['type' => 'varchar', 'required' => false, 'name' => 'status', 'default_value' => 'active', 'validators' => [['name' => 'maxlength', 'params' => 20]]],
        ];
        $orm = $this->createOrmMock('_test_scaff_def', 'id', $definitions);
        $scaffolder = new Mysql($orm);
        $scaffolder->run(false);

        $col = $this->getColumnInfo('_test_scaff_def', 'status');
        $this->assertNotNull($col);
        $this->assertSame('active', $col->Default);
    }

    public function testCreateTableWithoutAutoIncrement(): void
    {
        $definitions = [
            'id' => ['type' => 'varchar', 'required' => true, 'name' => 'id', 'validators' => [['name' => 'maxlength', 'params' => 36]]],
            'name' => ['type' => 'text', 'required' => false, 'name' => 'name'],
        ];
        $orm = $this->createOrmMock('_test_scaff_noai', 'id', $definitions, [], null, true);
        $scaffolder = new Mysql($orm);
        $scaffolder->run(false);

        $col = $this->getColumnInfo('_test_scaff_noai', 'id');
        $this->assertNotNull($col);
        $this->assertStringNotContainsString('auto_increment', strtolower($col->Extra));
    }

    public function testCreateTableWithCustomStorageEngine(): void
    {
        $definitions = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'data' => ['type' => 'text', 'required' => false, 'name' => 'data'],
        ];
        $orm = $this->createOrmMock('_test_scaff_engine', 'id', $definitions, [], 'myisam');
        $scaffolder = new Mysql($orm);
        $scaffolder->run(false);

        $status = $this->db->fetchAll("SHOW TABLE STATUS LIKE '_test_scaff_engine'");
        $this->assertNotEmpty($status);
        $this->assertSame('MyISAM', $status[0]->Engine);
    }

    public function testCreateTableSkipsRelationAndComputedColumns(): void
    {
        $definitions = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'link' => ['type' => 'relation', 'required' => false, 'name' => 'link'],
            'computed_val' => ['type' => 'computed', 'required' => false, 'name' => 'computed_val'],
            'title' => ['type' => 'text', 'required' => false, 'name' => 'title'],
        ];
        $orm = $this->createOrmMock('_test_scaff_skip', 'id', $definitions);
        $scaffolder = new Mysql($orm);
        $scaffolder->run(false);

        $columns = $this->db->fetchAll('DESCRIBE `_test_scaff_skip`');
        $names = array_map(fn($c) => $c->Field, $columns);
        $this->assertContains('id', $names);
        $this->assertContains('title', $names);
        $this->assertNotContains('link', $names);
        $this->assertNotContains('computed_val', $names);
    }

    public function testCreateTableWithPropertyLevelIndex(): void
    {
        $definitions = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'name' => ['type' => 'varchar', 'required' => false, 'name' => 'name', 'validators' => [['name' => 'maxlength', 'params' => 100]], 'index' => ['idx_name' => 'index']],
        ];
        $orm = $this->createOrmMock('_test_scaff_idx', 'id', $definitions);
        $scaffolder = new Mysql($orm);
        $scaffolder->run(false);

        $indexes = $this->db->fetchAll("SHOW INDEX FROM `_test_scaff_idx` WHERE Key_name = 'idx_name'");
        $this->assertNotEmpty($indexes);
    }

    public function testCreateTableWithFulltextIndex(): void
    {
        $definitions = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'content' => ['type' => 'text', 'required' => false, 'name' => 'content', 'index' => ['ft_content' => 'fulltext']],
        ];
        $orm = $this->createOrmMock('_test_scaff_ft', 'id', $definitions);
        $scaffolder = new Mysql($orm);
        $scaffolder->run(false);

        $indexes = $this->db->fetchAll("SHOW INDEX FROM `_test_scaff_ft` WHERE Key_name = 'ft_content'");
        $this->assertNotEmpty($indexes);
        $this->assertSame('FULLTEXT', $indexes[0]->Index_type);
    }

    public function testCreateTableWithEnumField(): void
    {
        $definitions = [
            'id' => ['type' => 'integer', 'required' => true, 'name' => 'id'],
            'status' => ['type' => 'string', 'required' => true, 'name' => 'status', 'validators' => [['name' => 'inarray', 'params' => ['active', 'inactive']]]],
        ];
        $orm = $this->createOrmMock('_test_scaff_enum', 'id', $definitions);
        $scaffolder = new Mysql($orm);
        $scaffolder->run(false);

        $col = $this->getColumnInfo('_test_scaff_enum', 'status');
        $this->assertNotNull($col);
        $this->assertStringContainsString('enum', strtolower($col->Type));
    }
}
