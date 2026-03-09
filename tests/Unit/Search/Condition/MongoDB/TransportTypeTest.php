<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\TransportType;

class TransportTypeTest extends TestCase
{
    public function testGetType(): void
    {
        $c = new TransportType('BUS');
        $this->assertSame('TransportType', $c->getType());
    }

    public function testFirstMatchQuery(): void
    {
        $c = new TransportType('FLUG');
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertEquals(['$in' => ['FLUG']], $query['prices']['$elemMatch']['transport_type']);
    }

    public function testFirstMatchQueryMultiple(): void
    {
        $c = new TransportType(['BUS', 'FLUG']);
        $query = $c->getQuery('first_match');
        $this->assertEquals(['$in' => ['BUS', 'FLUG']], $query['prices']['$elemMatch']['transport_type']);
    }

    public function testPricesFilterQuery(): void
    {
        $c = new TransportType('BUS');
        $query = $c->getQuery('prices_filter');
        $this->assertIsArray($query);
        $this->assertCount(1, $query);
    }

    public function testReturnsNullForUnknownType(): void
    {
        $c = new TransportType('BUS');
        $this->assertNull($c->getQuery('unknown'));
    }
}
