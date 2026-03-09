<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\Category;

class CategoryTest extends TestCase
{
    public function testGetType(): void
    {
        $c = new Category('destination', [1, 2]);
        $this->assertSame('Category', $c->getType());
    }

    public function testFirstMatchQuerySingleId(): void
    {
        $c = new Category('destination', 5);
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertSame('destination', $query['categories.field_name']);
        $this->assertSame(5, $query['categories.id_item']);
    }

    public function testFirstMatchQueryMultipleIdsOr(): void
    {
        $c = new Category('destination', [1, 2], 'OR');
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertArrayHasKey('$or', $query);
    }

    public function testFirstMatchQueryWithCategoryIdsNot(): void
    {
        $c = new Category('destination', [1], 'OR', [99]);
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertArrayHasKey('$and', $query);
    }

    public function testReturnsNullForUnknownType(): void
    {
        $c = new Category('x', 1);
        $this->assertNull($c->getQuery('prices_filter'));
    }

    public function testConstructorWithScalarCategoryIdsNot(): void
    {
        $c = new Category('dest', [1], 'OR', 99);
        $query = $c->getQuery('first_match');
        $this->assertArrayHasKey('$and', $query);
        $this->assertCount(2, $query['$and']);
    }

    public function testFirstMatchQueryMultipleIdsAnd(): void
    {
        $c = new Category('destination', [1, 2], 'AND');
        $query = $c->getQuery('first_match');
        $this->assertArrayHasKey('$and', $query);
    }
}
