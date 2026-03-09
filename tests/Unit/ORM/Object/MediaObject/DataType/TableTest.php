<?php

namespace Pressmind\Tests\Unit\ORM\Object\MediaObject\DataType;

use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\ORM\Object\MediaObject\DataType\Table;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class TableTest extends AbstractTestCase
{
    private function createTableRow(int $row, int $col, string $text, int $colspan = 1, string $style = '', int $width = 0, int $height = 0): \stdClass
    {
        $obj = new \stdClass();
        $obj->row = $row;
        $obj->col = $col;
        $obj->text = $text;
        $obj->colspan = $colspan;
        $obj->style = $style;
        $obj->width = $width;
        $obj->height = $height;
        return $obj;
    }

    /**
     * Creates a Table instance with a custom fetchAll result set injected via mock DB.
     */
    private function createTableWithDbRows(array $dbRows): Table
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('fetchAll')->willReturn($dbRows);
        $adapter->method('fetchRow')->willReturn(null);
        $adapter->method('fetchOne')->willReturn(null);
        $adapter->method('getAffectedRows')->willReturn(0);
        $adapter->method('getTablePrefix')->willReturn('pmt2core_');
        $adapter->method('inTransaction')->willReturn(false);
        $adapter->method('execute')->willReturn(null);
        $adapter->method('insert')->willReturn(null);
        $adapter->method('replace')->willReturn(null);
        $adapter->method('update')->willReturn(null);
        $adapter->method('delete')->willReturn(null);
        $adapter->method('truncate')->willReturn(null);
        $adapter->method('batchInsert')->willReturn(1);
        $adapter->method('beginTransaction')->willReturn(null);
        $adapter->method('commit')->willReturn(null);
        $adapter->method('rollback')->willReturn(null);

        Registry::getInstance()->add('db', $adapter);

        $table = new Table();
        $table->id = 1;
        return $table;
    }

    public function testGetReturnsStructuredArrayFromDbResults(): void
    {
        $table = $this->createTableWithDbRows([
            $this->createTableRow(1, 1, 'Header A'),
            $this->createTableRow(1, 2, 'Header B'),
            $this->createTableRow(2, 1, 'Cell A'),
            $this->createTableRow(2, 2, 'Cell B'),
        ]);

        $result = $table->get();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('Header A', $result[1][1]->text);
        $this->assertSame('Header B', $result[1][2]->text);
        $this->assertSame('Cell A', $result[2][1]->text);
        $this->assertSame('Cell B', $result[2][2]->text);
    }

    public function testGetReturnsEmptyArrayWhenNoData(): void
    {
        $table = new Table();
        $table->id = 99;

        $result = $table->get();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testAsHtmlReturnsNullWhenNoRows(): void
    {
        $table = new Table();
        $table->id = 99;

        $result = $table->asHTML();

        $this->assertNull($result);
    }

    public function testAsHtmlRendersTableWithTheadAndTbody(): void
    {
        $table = $this->createTableWithDbRows([
            $this->createTableRow(1, 1, 'Name'),
            $this->createTableRow(1, 2, 'Price'),
            $this->createTableRow(2, 1, 'Widget'),
            $this->createTableRow(2, 2, '9.99'),
        ]);

        $html = $table->asHTML('table table-hover', true);

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('class="table table-hover"', $html);
        $this->assertStringContainsString('<thead>', $html);
        $this->assertStringContainsString('<th', $html);
        $this->assertStringContainsString('Name', $html);
        $this->assertStringContainsString('Price', $html);
        $this->assertStringContainsString('Widget', $html);
        $this->assertStringContainsString('9.99', $html);
        $this->assertStringContainsString('</tbody>', $html);
        $this->assertStringContainsString('</table>', $html);
    }

    public function testAsHtmlWithoutTheadRendersAllAsTbody(): void
    {
        $table = $this->createTableWithDbRows([
            $this->createTableRow(1, 1, 'A'),
            $this->createTableRow(2, 1, 'B'),
        ]);

        $html = $table->asHTML('', false);

        $this->assertStringNotContainsString('<thead>', $html);
        $this->assertStringContainsString('<tbody>', $html);
        $this->assertStringContainsString('A', $html);
        $this->assertStringContainsString('B', $html);
    }

    public function testAsHtmlRendersColspan(): void
    {
        $table = $this->createTableWithDbRows([
            $this->createTableRow(1, 1, 'Spanning', 3),
            $this->createTableRow(2, 1, 'X'),
        ]);

        $html = $table->asHTML('table', true);

        $this->assertStringContainsString('colspan="3"', $html);
    }
}
