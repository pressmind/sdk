<?php

namespace Pressmind\Tests\Unit\Import\Mapper;

use Pressmind\Import\Mapper\Categorytree;
use Pressmind\Tests\Unit\AbstractTestCase;

class CategorytreeMapperTest extends AbstractTestCase
{
    public function testMapReturnsEmptyArrayWhenNoItems(): void
    {
        $mapper = new Categorytree();
        $input = (object) ['id_category' => 1, 'id_object_type' => 10];
        $result = $mapper->map(123, 'de', 'var_name', $input);
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testMapReturnsMappedItemsWithValidStructure(): void
    {
        $mapper = new Categorytree();
        $item = (object) [
            'id' => 'item-1',
            'is_primary' => true,
            'valid_from' => '2025-01-01',
            'valid_to' => '2025-12-31',
        ];
        $input = (object) [
            'id_category' => 5,
            'id_object_type' => 20,
            'items' => (object) [
                'level0' => [[$item]],
            ],
        ];
        $result = $mapper->map(999, 'en', 'category_field', $input);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $mapped = $result[0];
        $this->assertSame(999, $mapped->id_media_object);
        $this->assertSame('en', $mapped->language);
        $this->assertSame('category_field', $mapped->var_name);
        $this->assertSame(5, $mapped->id_tree);
        $this->assertSame('item-1', $mapped->id_item);
        $this->assertTrue($mapped->is_primary);
        $this->assertInstanceOf(\DateTime::class, $mapped->valid_from);
        $this->assertInstanceOf(\DateTime::class, $mapped->valid_to);
    }

    public function testMapSkipsDuplicateItemIds(): void
    {
        $mapper = new Categorytree();
        $item = (object) ['id' => 'dup', 'is_primary' => false, 'valid_from' => null, 'valid_to' => null];
        $input = (object) [
            'id_category' => 1,
            'id_object_type' => 1,
            'items' => (object) [
                'a' => [[$item, $item]],
            ],
        ];
        $result = $mapper->map(1, 'de', 'v', $input);
        $this->assertCount(1, $result);
    }
}
