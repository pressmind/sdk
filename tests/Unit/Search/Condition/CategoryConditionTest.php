<?php

namespace Pressmind\Tests\Unit\Search\Condition;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\Category;

class CategoryConditionTest extends TestCase
{
    public function testGetSqlAndValues(): void
    {
        $c = new Category('destination', [1, 2], 'OR');
        $sql = $c->getSQL();
        $this->assertStringContainsString("var_name = 'destination'", $sql);
        $this->assertStringContainsString('id_item', $sql);
        $values = $c->getValues();
        $this->assertCount(2, $values);
        $this->assertSame(3, $c->getSort());
    }

    public function testGetJoinsLinkedObjectSearch(): void
    {
        $c = new Category('dest', [1], 'OR', true);
        $joins = $c->getJoins();
        $this->assertStringContainsString('pmt2core_media_object_object_links', $joins);
        $this->assertSame('SUBSELECT', $c->getJoinType());
        $this->assertSame('dest', $c->getSubselectJoinTable());
    }

    public function testGetJoinsDefault(): void
    {
        $c = new Category('dest', [1], 'OR', false);
        $joins = $c->getJoins();
        $this->assertStringContainsString('pmt2core_media_object_tree_items', $joins);
    }

    public function testSetConfigAndCreate(): void
    {
        $config = new \stdClass();
        $config->var_name = 'x';
        $config->item_ids = [5];
        $config->combine_operator = 'AND';
        $config->linked_object_search = false;
        $c = Category::create('a', [1]);
        $c->setConfig($config);
        $this->assertSame('x', $c->var_name);
        $this->assertSame([5], $c->item_ids);
    }

    public function testCreateWithLinkedObjectSearch(): void
    {
        $c = Category::create('dest', [1, 2], 'OR', true);
        $this->assertInstanceOf(Category::class, $c);
        $joins = $c->getJoins();
        $this->assertStringContainsString('pmt2core_media_object_object_links', $joins);
        $this->assertSame('SUBSELECT', $c->getJoinType());
        $this->assertSame('dest', $c->getSubselectJoinTable());
    }

    public function testGetAdditionalFields(): void
    {
        $c = new Category('x', [1]);
        $this->assertNull($c->getAdditionalFields());
    }
}
