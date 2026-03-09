<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Tests AbstractObject::batchCreate() with mocked DB adapter.
 */
class AbstractObjectBatchCreateTest extends AbstractTestCase
{
    private AdapterInterface $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createMock(AdapterInterface::class);
        $this->db->method('getTablePrefix')->willReturn('pmt2core_');
        Registry::getInstance()->add('db', $this->db);
    }

    public function testBatchCreateEmptyReturnsZero(): void
    {
        $this->assertSame(0, AbstractObject::batchCreate([]));
    }

    public function testBatchCreateCallsBatchInsertOnAdapter(): void
    {
        $obj1 = new TestEntityStub(null, false);
        $obj1->id = 'id1';
        $obj1->name = 'Test 1';
        $obj2 = new TestEntityStub(null, false);
        $obj2->id = 'id2';
        $obj2->name = 'Test 2';

        $this->db->expects($this->once())
            ->method('batchInsert')
            ->with(
                $this->equalTo('pmt2core_test_entity'),
                $this->callback(function ($cols) {
                    return in_array('id', $cols) && in_array('name', $cols);
                }),
                $this->callback(function ($rows) {
                    return count($rows) === 2 && count($rows[0]) === 2 && count($rows[1]) === 2;
                }),
                $this->isFalse()
            )
            ->willReturn(2);

        $count = AbstractObject::batchCreate([$obj1, $obj2]);
        $this->assertSame(2, $count);
    }
}

/**
 * Minimal AbstractObject implementation for batchCreate tests.
 */
class TestEntityStub extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = [
        'class' => ['name' => self::class],
        'database' => [
            'table_name' => 'test_entity',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'name' => 'id',
                'title' => 'id',
                'type' => 'string',
                'required' => true,
            ],
            'name' => [
                'name' => 'name',
                'title' => 'name',
                'type' => 'string',
                'required' => true,
            ],
        ],
    ];
}
