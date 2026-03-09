<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\StartingPointOptionCity;

class StartingPointOptionCityTest extends TestCase
{
    public function testGetType(): void
    {
        $c = new StartingPointOptionCity('city_1');
        $this->assertSame('StartingPointOptionCity', $c->getType());
    }

    public function testFirstMatchQuery(): void
    {
        $c = new StartingPointOptionCity(['c1', 'c2']);
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertArrayHasKey('prices.startingpoint_option.id_city', $query);
        $this->assertEquals(['$in' => ['c1', 'c2']], $query['prices.startingpoint_option.id_city']);
    }

    public function testStageAfterMatchQuery(): void
    {
        $c = new StartingPointOptionCity('c1');
        $query = $c->getQuery('stage_after_match');
        $this->assertIsArray($query);
        $this->assertArrayHasKey('$set', $query);
        $this->assertArrayHasKey('prices', $query['$set']);
    }

    public function testReturnsNullWhenEmptyCities(): void
    {
        $c = new StartingPointOptionCity([]);
        $this->assertNull($c->getQuery('first_match'));
    }

    public function testReturnsNullForUnknownType(): void
    {
        $c = new StartingPointOptionCity('c1');
        $this->assertNull($c->getQuery('unknown'));
    }
}
