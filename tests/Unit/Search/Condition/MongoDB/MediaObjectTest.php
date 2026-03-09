<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\MediaObject;

class MediaObjectTest extends TestCase
{
    public function testGetType(): void
    {
        $c = new MediaObject(123);
        $this->assertSame('MediaObject', $c->getType());
    }

    public function testGetValue(): void
    {
        $c = new MediaObject([1, 2]);
        $this->assertSame([1, 2], $c->getValue());
    }

    public function testFirstMatchQueryIn(): void
    {
        $c = new MediaObject(123);
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertEquals(['$in' => [123]], $query['id_media_object']);
    }

    public function testFirstMatchQueryNotIn(): void
    {
        $c = new MediaObject(-456);
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertEquals(['$nin' => [456]], $query['id_media_object']);
    }

    public function testFirstMatchQueryMixedInAndNotIn(): void
    {
        $c = new MediaObject([1, -2]);
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertArrayHasKey('$and', $query);
    }

    public function testReturnsNullForUnknownType(): void
    {
        $c = new MediaObject(1);
        $this->assertNull($c->getQuery('prices_filter'));
    }
}
