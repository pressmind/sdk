<?php

namespace Pressmind\Tests\Unit\Search\Condition;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\DataView;

/**
 * DataView condition: test only constructor, create, and getSQL/getValues/getSort
 * when setConfig() has not been called (no DB/ORM required).
 */
class DataViewTest extends TestCase
{
    public function testCreate(): void
    {
        $c = DataView::create('test-view');
        $this->assertInstanceOf(DataView::class, $c);
    }

    public function testGetSqlBeforeSetConfig(): void
    {
        $c = new DataView('x', null);
        $this->assertSame('', $c->getSQL());
    }

    public function testGetValuesAndSortBeforeSetConfig(): void
    {
        $c = new DataView(null, 1);
        $this->assertSame([], $c->getValues());
        $this->assertSame(1, $c->getSort());
    }

    public function testGetJoinsBeforeSetConfig(): void
    {
        $c = new DataView('x');
        $this->assertSame('', $c->getJoins());
        $this->assertNull($c->getJoinType());
        $this->assertNull($c->getSubselectJoinTable());
        $this->assertNull($c->getAdditionalFields());
    }

    public function testGetConfig(): void
    {
        $c = new DataView('my-view');
        $cfg = $c->getConfig();
        $this->assertArrayHasKey('name', $cfg);
        // name is only set via setConfig(); constructor does not assign it, so value may be null
    }

    public function testSetConfigThrowsWhenNameAndIdNull(): void
    {
        $c = new DataView(null, null);
        $config = new \stdClass();
        $config->name = null;
        $config->id = null;
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing parameters!');
        $c->setConfig($config);
    }

    public function testCreateWithId(): void
    {
        $c = DataView::create(null, 42);
        $this->assertInstanceOf(DataView::class, $c);
    }

    public function testCreateWithBothParams(): void
    {
        $c = DataView::create('test', 5);
        $this->assertInstanceOf(DataView::class, $c);
    }

    public function testGetConfigReflectsDirectPropertyAssignment(): void
    {
        $c = new DataView();
        $c->name = 'assigned-view';
        $cfg = $c->getConfig();
        $this->assertSame('assigned-view', $cfg['name']);
    }

    public function testConstructorDefaultArgs(): void
    {
        $c = new DataView();
        $this->assertSame('', $c->getSQL());
        $this->assertSame([], $c->getValues());
        $this->assertSame(1, $c->getSort());
        $this->assertSame('', $c->getJoins());
    }
}
