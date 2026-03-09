<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\SalesPriority;

class SalesPriorityTest extends TestCase
{
    public function testGetType(): void
    {
        $c = new SalesPriority('high');
        $this->assertSame('SalesPriority', $c->getType());
    }

    public function testFirstMatchQuery(): void
    {
        $c = new SalesPriority('high');
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertSame('high', $query['sales_priority']);
    }

    public function testReturnsNullForUnknownType(): void
    {
        $c = new SalesPriority('low');
        $this->assertNull($c->getQuery('unknown'));
    }
}
