<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * hasMany relations with related_foreign_id are read-only and must not be written back
 * by create() / batchCreate(), while regular hasMany relations are still created.
 */
class AbstractObjectCreateHasManyRelationsTest extends AbstractTestCase
{
    private AdapterInterface $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createMock(AdapterInterface::class);
        $this->db->method('getTablePrefix')->willReturn('pmt2core_');
        Registry::getInstance()->add('db', $this->db);
    }

    public function testCreateSkipsHasManyRelationWithRelatedForeignId(): void
    {
        $parent = $this->createParent();
        $parent->foreign_children = [$this->createChild('c1')];

        $this->db->expects($this->once())
            ->method('insert')
            ->with($this->equalTo('pmt2core_test_parent'));

        $parent->create();
    }

    public function testCreateStillCreatesRegularHasManyRelation(): void
    {
        $parent = $this->createParent();
        $parent->children = [$this->createChild('c1')];

        $tables = [];
        $this->db->expects($this->exactly(2))
            ->method('insert')
            ->willReturnCallback(function ($table) use (&$tables) {
                $tables[] = $table;
                return null;
            });

        $parent->create();

        $this->assertSame(['pmt2core_test_parent', 'pmt2core_test_child'], $tables);
        $this->assertSame('p1', $parent->children[0]->id_parent);
    }

    public function testBatchCreateSkipsHasManyRelationWithRelatedForeignId(): void
    {
        $parent = $this->createParent();
        $parent->foreign_children = [$this->createChild('c1')];

        $this->db->expects($this->once())
            ->method('batchInsert')
            ->with($this->equalTo('pmt2core_test_parent'))
            ->willReturn(1);

        AbstractObject::batchCreate([$parent]);
    }

    private function createParent(): TestParentStub
    {
        $parent = new TestParentStub(null, false);
        $parent->id = 'p1';
        $parent->id_foreign = 'f1';
        return $parent;
    }

    private function createChild(string $id): TestChildStub
    {
        $child = new TestChildStub(null, false);
        $child->id = $id;
        return $child;
    }
}

class TestParentStub extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = [
        'class' => ['name' => self::class],
        'database' => [
            'table_name' => 'test_parent',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => ['name' => 'id', 'title' => 'id', 'type' => 'string', 'required' => true],
            'id_foreign' => ['name' => 'id_foreign', 'title' => 'id_foreign', 'type' => 'string', 'required' => false],
            'children' => [
                'name' => 'children',
                'title' => 'children',
                'type' => 'relation',
                'required' => false,
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_parent',
                    'class' => TestChildStub::class,
                ],
            ],
            'foreign_children' => [
                'name' => 'foreign_children',
                'title' => 'foreign_children',
                'type' => 'relation',
                'required' => false,
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_foreign',
                    'related_foreign_id' => 'id_parent',
                    'class' => TestChildStub::class,
                    'prevent_auto_delete' => true,
                ],
            ],
        ],
    ];
}

class TestChildStub extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = [
        'class' => ['name' => self::class],
        'database' => [
            'table_name' => 'test_child',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => ['name' => 'id', 'title' => 'id', 'type' => 'string', 'required' => true],
            'id_parent' => ['name' => 'id_parent', 'title' => 'id_parent', 'type' => 'string', 'required' => false],
        ],
    ];
}
