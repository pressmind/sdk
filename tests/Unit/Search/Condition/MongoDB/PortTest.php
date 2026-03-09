<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\Port;

class PortTest extends TestCase
{
    public function testGetType(): void
    {
        $c = new Port('port1');
        $this->assertSame('Port', $c->getType());
    }

    public function testFirstMatchQuery(): void
    {
        $c = new Port(['p1', 'p2']);
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertArrayHasKey('ports.id', $query);
        $this->assertSame(['$in' => ['p1', 'p2']], $query['ports.id']);
    }

    public function testFirstMatchQuerySinglePort(): void
    {
        $c = new Port('p1');
        $query = $c->getQuery('first_match');
        $this->assertSame(['$in' => ['p1']], $query['ports.id']);
    }

    public function testReturnsNullForUnknownType(): void
    {
        $c = new Port('p1');
        $this->assertNull($c->getQuery('unknown'));
    }
}
