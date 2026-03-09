<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\Powerfilter;

class PowerfilterTest extends TestCase
{
    public function testGetType(): void
    {
        $c = new Powerfilter(5);
        $this->assertSame('Powerfilter', $c->getType());
    }

    public function testGetValue(): void
    {
        $c = new Powerfilter(5);
        $this->assertSame(5, $c->getValue());
    }

    public function testLookupQuery(): void
    {
        $c = new Powerfilter(10);
        $query = $c->getQuery('lookup');
        $this->assertIsArray($query);
        $this->assertArrayHasKey('$lookup', $query);
        $this->assertSame('powerfilter', $query['$lookup']['from']);
    }

    public function testMatchQuery(): void
    {
        $c = new Powerfilter(10);
        $query = $c->getQuery('match');
        $this->assertIsArray($query);
        $this->assertArrayHasKey('$match', $query);
        $this->assertSame(10, $query['$match']['matchedPowerfilter._id']);
    }

    public function testReturnsNullForFirstMatch(): void
    {
        $c = new Powerfilter(1);
        $this->assertNull($c->getQuery('first_match'));
    }

    public function testLookupQueryStructure(): void
    {
        $c = new Powerfilter(99);
        $query = $c->getQuery('lookup');
        $lookup = $query['$lookup'];
        $this->assertSame('powerfilter', $lookup['from']);
        $this->assertSame('_id', $lookup['localField']);
        $this->assertSame('id_media_objects', $lookup['foreignField']);
        $this->assertSame('matchedPowerfilter', $lookup['as']);
    }

    public function testValueCastToInt(): void
    {
        $c = new Powerfilter('42');
        $this->assertSame(42, $c->getValue());
    }

    public function testPricesFilterReturnsNull(): void
    {
        $c = new Powerfilter(1);
        $this->assertNull($c->getQuery('prices_filter'));
    }
}
