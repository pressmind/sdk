<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\Occupancy;

class OccupancyTest extends TestCase
{
    public function testGetType(): void
    {
        $c = new Occupancy([2, 3]);
        $this->assertSame('Occupancy', $c->getType());
    }

    public function testGetOccupancies(): void
    {
        $c = new Occupancy([2, 3]);
        $this->assertEqualsCanonicalizing([2, 3], $c->getOccupancies());
    }

    public function testFirstMatchQuery(): void
    {
        $c = new Occupancy([2, 3]);
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertArrayHasKey('prices', $query);
        $this->assertArrayHasKey('$elemMatch', $query['prices']);
        $this->assertArrayHasKey('occupancy', $query['prices']['$elemMatch']);
    }

    public function testFirstMatchQueryWithAllowInvalidOffers(): void
    {
        $c = new Occupancy([2]);
        $query = $c->getQuery('first_match', true);
        $this->assertIsArray($query);
        $this->assertArrayHasKey('$or', $query);
    }

    public function testPricesFilterQuery(): void
    {
        $c = new Occupancy([2]);
        $query = $c->getQuery('prices_filter');
        $this->assertIsArray($query);
        $this->assertCount(1, $query);
    }

    public function testReturnsNullForUnknownType(): void
    {
        $c = new Occupancy(2);
        $this->assertNull($c->getQuery('unknown'));
    }

    public function testConstructorWithScalarChildOccupancies(): void
    {
        $c = new Occupancy([2], 1);
        $this->assertEqualsCanonicalizing([1], $c->getChildOccupancies());
    }

    public function testFirstMatchQueryWithChildOccupancies(): void
    {
        $c = new Occupancy([2], [1, 2]);
        $query = $c->getQuery('first_match');
        $this->assertArrayHasKey('prices', $query);
        $this->assertArrayHasKey('occupancy_child', $query['prices']['$elemMatch']);
        $this->assertEquals(['$in' => [null, 1, 2]], $query['prices']['$elemMatch']['occupancy_child']);
    }

    public function testFirstMatchQueryAllowInvalidOffersWithChildOccupancies(): void
    {
        $c = new Occupancy([2], [1, 2]);
        $query = $c->getQuery('first_match', true);
        $this->assertArrayHasKey('$or', $query);
        $this->assertArrayHasKey('occupancy_child', $query['$or'][0]['prices']['$elemMatch']);
    }
}
