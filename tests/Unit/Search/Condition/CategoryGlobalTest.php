<?php

namespace Pressmind\Tests\Unit\Search\Condition;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\CategoryGlobal;

/**
 * Unit tests for Pressmind\Search\Condition\CategoryGlobal.
 */
class CategoryGlobalTest extends TestCase
{
    public function testCreate(): void
    {
        $c = CategoryGlobal::create([10, 20]);
        $this->assertInstanceOf(CategoryGlobal::class, $c);
        $this->assertSame([10, 20], $c->item_ids);
    }

    public function testCreateWithCombineOperator(): void
    {
        $c = CategoryGlobal::create([1], 'AND');
        $sql = $c->getSQL();
        $this->assertStringContainsString('id_item', $sql);
    }

    public function testGetSqlAndValues(): void
    {
        $c = new CategoryGlobal([1, 2], 'OR');
        $sql = $c->getSQL();
        $this->assertStringContainsString('id_item = :category_item1', $sql);
        $this->assertStringContainsString('OR', $sql);
        $this->assertStringContainsString('id_item = :category_item2', $sql);
        $values = $c->getValues();
        $this->assertCount(2, $values);
        $this->assertSame(1, $values[':category_item1']);
        $this->assertSame(2, $values[':category_item2']);
        $this->assertSame(3, $c->getSort());
    }

    public function testGetSqlWithAnd(): void
    {
        $c = new CategoryGlobal([1, 2], 'AND');
        $sql = $c->getSQL();
        $this->assertStringContainsString(' AND ', $sql);
    }

    public function testGetSqlWithEmptyItemIds(): void
    {
        $c = new CategoryGlobal([], 'OR');
        $sql = $c->getSQL();
        $this->assertSame('()', $sql);
        $this->assertEmpty($c->getValues());
    }

    public function testGetJoinsAndNulls(): void
    {
        $c = new CategoryGlobal([1]);
        $this->assertStringContainsString('INNER JOIN pmt2core_media_object_tree_items', $c->getJoins());
        $this->assertNull($c->getJoinType());
        $this->assertNull($c->getSubselectJoinTable());
        $this->assertNull($c->getAdditionalFields());
    }

    public function testSetConfig(): void
    {
        $c = new CategoryGlobal([1]);
        $config = new \stdClass();
        $config->item_ids = [100, 200];
        $config->combine_operator = 'AND';
        $c->setConfig($config);
        $this->assertSame([100, 200], $c->item_ids);
        $sql = $c->getSQL();
        $this->assertStringContainsString(' AND ', $sql);
    }

    public function testSetConfigDefaultCombineOperator(): void
    {
        $c = new CategoryGlobal([1]);
        $config = new \stdClass();
        $config->item_ids = [5];
        $c->setConfig($config);
        $sql = $c->getSQL();
        $this->assertStringContainsString('category_item1', $sql);
    }
}
