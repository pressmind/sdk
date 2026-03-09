<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\Group;

class GroupTest extends TestCase
{
    public function testGetType(): void
    {
        $c = new Group(['g1']);
        $this->assertSame('Group', $c->getType());
    }

    public function testFirstMatchQuery(): void
    {
        $c = new Group(['g1', 'g2']);
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertArrayHasKey('$or', $query);
        $this->assertGreaterThanOrEqual(2, count($query['$or']));
    }

    public function testReturnsNullForUnknownType(): void
    {
        $c = new Group('g1');
        $this->assertNull($c->getQuery('prices_filter'));
    }
}
