<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\PriceRange;

class PriceRangeTest extends TestCase
{
    public function testGetType(): void
    {
        $c = new PriceRange(100, 500);
        $this->assertSame('PriceRange', $c->getType());
    }

    public function testFirstMatchQuery(): void
    {
        $c = new PriceRange(100, 500);
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertArrayHasKey('prices', $query);
        $this->assertArrayHasKey('$elemMatch', $query['prices']);
        $this->assertEquals(['$gte' => 100, '$lte' => 500], $query['prices']['$elemMatch']['price_total']);
    }

    public function testPricesFilterQuery(): void
    {
        $c = new PriceRange(100, 500);
        $query = $c->getQuery('prices_filter');
        $this->assertIsArray($query);
        $this->assertCount(2, $query);
    }

    public function testReturnsNullForUnknownType(): void
    {
        $c = new PriceRange(0, 100);
        $this->assertNull($c->getQuery('unknown'));
    }

    public function testPricesFilterQueryStructure(): void
    {
        $c = new PriceRange(200, 1000);
        $query = $c->getQuery('prices_filter');
        $this->assertSame(['$gte' => ['$$this.price_total', 200]], $query[0]);
        $this->assertSame(['$lte' => ['$$this.price_total', 1000]], $query[1]);
    }

    public function testConstructorCastsToInt(): void
    {
        $c = new PriceRange('50', '999');
        $query = $c->getQuery('first_match');
        $this->assertSame(50, $query['prices']['$elemMatch']['price_total']['$gte']);
        $this->assertSame(999, $query['prices']['$elemMatch']['price_total']['$lte']);
    }
}
