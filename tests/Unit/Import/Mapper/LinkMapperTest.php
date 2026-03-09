<?php

namespace Pressmind\Tests\Unit\Import\Mapper;

use Pressmind\Import\Mapper\Link;
use Pressmind\Tests\Unit\AbstractTestCase;

class LinkMapperTest extends AbstractTestCase
{
    public function testMapReturnsEmptyWhenObjectNull(): void
    {
        $mapper = new Link();
        $result = $mapper->map(1, 'de', 'var', null);
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testMapReturnsMappedLink(): void
    {
        $mapper = new Link();
        $object = (object) [
            'hrefLink' => 'https://example.com',
            'link_type' => 'external',
        ];
        $result = $mapper->map(42, 'en', 'link1', $object);
        $this->assertCount(1, $result);
        $mapped = $result[0];
        $this->assertSame(42, $mapped->id_media_object);
        $this->assertSame('en', $mapped->language);
        $this->assertSame('link1', $mapped->var_name);
        $this->assertSame('https://example.com', $mapped->href);
        $this->assertSame('external', $mapped->link_type);
    }

    public function testMapHandlesMissingOptionalFields(): void
    {
        $mapper = new Link();
        $object = (object) [];
        $result = $mapper->map(1, 'de', 'v', $object);
        $this->assertCount(1, $result);
        $this->assertNull($result[0]->href);
        $this->assertNull($result[0]->link_type);
    }
}
