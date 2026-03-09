<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\SoldOut;

class SoldOutTest extends TestCase
{
    public function testGetType(): void
    {
        $c = new SoldOut(false);
        $this->assertSame('SoldOut', $c->getType());
    }

    public function testFirstMatchQueryTrue(): void
    {
        $c = new SoldOut(true);
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertSame(true, $query['sold_out']);
    }

    public function testFirstMatchQueryFalse(): void
    {
        $c = new SoldOut(false);
        $query = $c->getQuery('first_match');
        $this->assertSame(false, $query['sold_out']);
    }

    public function testReturnsNullForUnknownType(): void
    {
        $c = new SoldOut(false);
        $this->assertNull($c->getQuery('prices_filter'));
    }
}
