<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\Running;

class RunningTest extends TestCase
{
    public function testGetType(): void
    {
        $c = new Running(true);
        $this->assertSame('Running', $c->getType());
    }

    public function testFirstMatchQuery(): void
    {
        $c = new Running(true);
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertSame(true, $query['is_running']);
    }

    public function testReturnsNullForUnknownType(): void
    {
        $c = new Running(false);
        $this->assertNull($c->getQuery('unknown'));
    }
}
