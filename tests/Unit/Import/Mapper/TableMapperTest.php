<?php

namespace Pressmind\Tests\Unit\Import\Mapper;

use Pressmind\Import\Mapper\Table;
use Pressmind\Tests\Unit\AbstractTestCase;

class TableMapperTest extends AbstractTestCase
{
    public function testMapReturnsEmptyWhenObjectNull(): void
    {
        $mapper = new Table();
        $result = $mapper->map(1, 'de', 'var', null);
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testMapReturnsMappedTableWithRows(): void
    {
        $mapper = new Table();
        $object = (object) [
            'table' => [
                (object) [
                    'cols' => [
                        (object) [
                            'colspan' => 1,
                            'id_style' => null,
                            'height' => null,
                            'width' => null,
                            'text' => 'Cell 1',
                        ],
                    ],
                ],
            ],
        ];
        $result = $mapper->map(42, 'en', 'table1', $object);
        $this->assertCount(1, $result);
        $mapped = $result[0];
        $this->assertSame(42, $mapped->id_media_object);
        $this->assertSame('en', $mapped->language);
        $this->assertSame('table1', $mapped->var_name);
        $this->assertIsArray($mapped->rows);
        $this->assertCount(1, $mapped->rows);
    }

    public function testMapHandlesEmptyTable(): void
    {
        $mapper = new Table();
        $object = (object) ['table' => []];
        $result = $mapper->map(1, 'de', 'v', $object);
        $this->assertCount(1, $result);
        $this->assertCount(0, $result[0]->rows);
    }

    public function testMapHandlesMissingTableKey(): void
    {
        $mapper = new Table();
        $object = (object) [];
        $result = $mapper->map(1, 'de', 'v', $object);
        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]->id_media_object);
    }
}
