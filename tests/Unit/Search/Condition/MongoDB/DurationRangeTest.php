<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\DurationRange;

class DurationRangeTest extends TestCase
{
    public function testGetType(): void
    {
        $c = new DurationRange(7, 14);
        $this->assertSame('DurationRange', $c->getType());
    }

    public function testGetDurationFromAndTo(): void
    {
        $c = new DurationRange(7, 14);
        $this->assertSame(7.0, $c->getDurationFrom());
        $this->assertSame(14.9, $c->getDurationTo());
    }

    public function testFirstMatchQuery(): void
    {
        $c = new DurationRange(7, 14);
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertEquals(['$gte' => 7.0, '$lte' => 14.9], $query['prices']['$elemMatch']['duration']);
    }

    public function testPricesFilterQuery(): void
    {
        $c = new DurationRange(3, 7);
        $query = $c->getQuery('prices_filter');
        $this->assertIsArray($query);
        $this->assertCount(2, $query);
    }

    public function testReturnsNullForUnknownType(): void
    {
        $c = new DurationRange(1, 7);
        $this->assertNull($c->getQuery('unknown'));
    }

    public function testPricesFilterQueryStructure(): void
    {
        $c = new DurationRange(5, 10);
        $query = $c->getQuery('prices_filter');
        $this->assertSame(['$gte' => ['$$this.duration', 5.0]], $query[0]);
        $this->assertSame(['$lte' => ['$$this.duration', 10.9]], $query[1]);
    }

    public function testDurationToAppends9(): void
    {
        $c = new DurationRange(3, 21);
        $this->assertSame(21.9, $c->getDurationTo());
    }
}
