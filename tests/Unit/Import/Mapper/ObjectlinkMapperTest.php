<?php

namespace Pressmind\Tests\Unit\Import\Mapper;

use Pressmind\Import\Mapper\Objectlink;
use Pressmind\Tests\Unit\AbstractTestCase;

class ObjectlinkMapperTest extends AbstractTestCase
{
    public function testMapReturnsEmptyArrayWhenNoObjects(): void
    {
        $mapper = new Objectlink();
        $input = (object) ['id_object_type' => 1];
        $result = $mapper->map(1, 'de', 'var', $input);
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testMapReturnsMappedObjectLinks(): void
    {
        $mapper = new Objectlink();
        $input = (object) [
            'objects' => [100, 200],
            'id_object_type' => 5,
            'objectLink' => 1,
        ];
        $result = $mapper->map(42, 'en', 'related', $input);
        $this->assertCount(2, $result);
        $this->assertSame(42, $result[0]->id_media_object);
        $this->assertSame(100, $result[0]->id_media_object_link);
        $this->assertSame(5, $result[0]->id_object_type);
        $this->assertSame('objectlink', $result[0]->link_type);
        $this->assertSame(200, $result[1]->id_media_object_link);
    }

    public function testMapUsesImageLinkTypeWhenObjectLinkIsNotOne(): void
    {
        $mapper = new Objectlink();
        $input = (object) [
            'objects' => [1],
            'id_object_type' => 2,
            'objectLink' => 0,
        ];
        $result = $mapper->map(1, 'de', 'v', $input);
        $this->assertSame('image', $result[0]->link_type);
    }
}
