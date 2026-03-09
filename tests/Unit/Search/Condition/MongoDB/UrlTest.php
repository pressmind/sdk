<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\Url;

class UrlTest extends TestCase
{
    public function testGetType(): void
    {
        $c = new Url('/path');
        $this->assertSame('Url', $c->getType());
    }

    public function testFirstMatchQuerySingle(): void
    {
        $c = new Url('/single-path');
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertSame('/single-path', $query['url']);
    }

    public function testFirstMatchQueryMultiple(): void
    {
        $c = new Url(['/a', '/b'], 'OR');
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertArrayHasKey('$or', $query);
    }

    public function testReturnsNullForUnknownType(): void
    {
        $c = new Url('/x');
        $this->assertNull($c->getQuery('unknown'));
    }
}
