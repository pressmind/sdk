<?php

namespace Pressmind\Tests\Unit\Import\Mapper;

use Pressmind\Import\Mapper\Key_value;
use Pressmind\Tests\Unit\AbstractTestCase;

class Key_valueMapperTest extends AbstractTestCase
{
    public function testMapReturnsEmptyWhenObjectNull(): void
    {
        $mapper = new Key_value();
        $result = $mapper->map(1, 'de', 'var', null);
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testMapReturnsEmptyWhenValuesEmpty(): void
    {
        $mapper = new Key_value();
        $object = [
            'columns' => [],
            'values' => [],
        ];
        $result = $mapper->map(1, 'de', 'var', $object);
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testMapReturnsMappedRowsWithPlaintextColumn(): void
    {
        $mapper = new Key_value();
        $object = [
            'columns' => [
                (object) ['sort' => 0, 'name' => 'Col1', 'var_name' => 'col1', 'type' => 'PLAINTEXT'],
            ],
            'values' => [
                (object) ['sort' => 0, 'value_0_string' => 'Hello'],
            ],
        ];
        $result = $mapper->map(42, 'de', 'table1', $object);
        $this->assertCount(1, $result);
        $this->assertSame(42, $result[0]->id_media_object);
        $this->assertSame('de', $result[0]->language);
        $this->assertSame('table1', $result[0]->var_name);
        $this->assertIsArray($result[0]->rows);
    }

    public function testMapHandlesIntegerColumn(): void
    {
        $mapper = new Key_value();
        $object = [
            'columns' => [
                (object) ['sort' => 0, 'name' => 'Num', 'var_name' => 'num', 'type' => 'INTEGER'],
            ],
            'values' => [
                (object) ['sort' => 0, 'value_0_int' => 100],
            ],
        ];
        $result = $mapper->map(1, 'en', 'v', $object);
        $this->assertCount(1, $result);
    }

    public function testMapHandlesNumberColumn(): void
    {
        $mapper = new Key_value();
        $object = [
            'columns' => [
                (object) ['sort' => 0, 'name' => 'Price', 'var_name' => 'price', 'type' => 'NUMBER'],
            ],
            'values' => [
                (object) ['sort' => 0, 'value_0_decimal' => 19.99],
            ],
        ];
        $result = $mapper->map(1, 'en', 'v', $object);
        $this->assertCount(1, $result);
    }
}
