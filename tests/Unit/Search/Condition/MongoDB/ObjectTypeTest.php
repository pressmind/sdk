<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\ObjectType;

class ObjectTypeTest extends TestCase
{
    public function testGetType(): void
    {
        $c = new ObjectType(100);
        $this->assertSame('ObjectType', $c->getType());
    }

    public function testGetObjectTypes(): void
    {
        $c = new ObjectType([100, 200]);
        $this->assertSame([100, 200], $c->getObjectTypes());
    }

    public function testFirstMatchQuerySingle(): void
    {
        $c = new ObjectType(100);
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertSame(100, $query['id_object_type']);
    }

    public function testFirstMatchQueryArray(): void
    {
        $c = new ObjectType([100, 200]);
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertEquals(['$in' => [100, 200]], $query['id_object_type']);
    }

    public function testReturnsNullForUnknownType(): void
    {
        $c = new ObjectType(100);
        $this->assertNull($c->getQuery('unknown'));
    }

    public function testGetObjectTypesWithSingleId(): void
    {
        $c = new ObjectType(100);
        $this->assertSame([100], $c->getObjectTypes());
    }

    public function testConstructorWithSingleElementArray(): void
    {
        $c = new ObjectType([100]);
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertArrayHasKey('id_object_type', $query);
    }
}
