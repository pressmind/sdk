<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\Guaranteed;

class GuaranteedTest extends TestCase
{
    public function testGetType(): void
    {
        $c = new Guaranteed(true);
        $this->assertSame('Guaranteed', $c->getType());
    }

    public function testFirstMatchQuery(): void
    {
        $c = new Guaranteed(true);
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertSame(true, $query['has_guaranteed_departures']);
    }

    public function testReturnsNullForUnknownType(): void
    {
        $c = new Guaranteed(false);
        $this->assertNull($c->getQuery('unknown'));
    }
}
