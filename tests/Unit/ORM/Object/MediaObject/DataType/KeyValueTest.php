<?php

namespace Pressmind\Tests\Unit\ORM\Object\MediaObject\DataType;

use Pressmind\ORM\Object\MediaObject\DataType\Key_value;
use Pressmind\Tests\Unit\AbstractTestCase;

class KeyValueTest extends AbstractTestCase
{
    public function testAsHtmlReturnsTableWithRows(): void
    {
        $kv = new Key_value();

        $col1 = new \stdClass();
        $col1->var_name = 'label';
        $col1->datatype = 'string';
        $col1->value_string = 'Name';
        $col1->class = null;

        $col2 = new \stdClass();
        $col2->var_name = 'data';
        $col2->datatype = 'string';
        $col2->value_string = 'John';
        $col2->class = null;

        $row1 = new \stdClass();
        $row1->columns = [$col1, $col2];

        $col3 = new \stdClass();
        $col3->var_name = 'label';
        $col3->datatype = 'string';
        $col3->value_string = 'Age';
        $col3->class = null;

        $col4 = new \stdClass();
        $col4->var_name = 'data';
        $col4->datatype = 'string';
        $col4->value_string = '30';
        $col4->class = null;

        $row2 = new \stdClass();
        $row2->columns = [$col3, $col4];

        $mock = $this->getMockBuilder(Key_value::class)
            ->onlyMethods(['toStdClass'])
            ->getMock();

        $stdObj = new \stdClass();
        $stdObj->rows = [$row1, $row2];
        $mock->method('toStdClass')->willReturn($stdObj);

        $html = $mock->asHTML('table table-hover', true);

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('</table>', $html);
        $this->assertStringContainsString('Name', $html);
        $this->assertStringContainsString('John', $html);
        $this->assertStringContainsString('Age', $html);
        $this->assertStringContainsString('30', $html);
    }

    public function testAsHtmlReturnsNullWhenNoRows(): void
    {
        $mock = $this->getMockBuilder(Key_value::class)
            ->onlyMethods(['toStdClass'])
            ->getMock();

        $stdObj = new \stdClass();
        $stdObj->rows = [];
        $mock->method('toStdClass')->willReturn($stdObj);

        $result = $mock->asHTML();

        $this->assertNull($result);
    }

    public function testAsHtmlAppliesTableClass(): void
    {
        $col = new \stdClass();
        $col->var_name = 'key';
        $col->datatype = 'string';
        $col->value_string = 'test';
        $col->class = null;

        $row = new \stdClass();
        $row->columns = [$col];

        $mock = $this->getMockBuilder(Key_value::class)
            ->onlyMethods(['toStdClass'])
            ->getMock();

        $stdObj = new \stdClass();
        $stdObj->rows = [$row];
        $mock->method('toStdClass')->willReturn($stdObj);

        $html = $mock->asHTML('custom-class', false);

        $this->assertStringContainsString('class="custom-class"', $html);
        $this->assertStringNotContainsString('<thead>', $html);
    }

    public function testAsHtmlRendersFirstRowAsThead(): void
    {
        $col1 = new \stdClass();
        $col1->var_name = 'header';
        $col1->datatype = 'string';
        $col1->value_string = 'Header';
        $col1->class = null;

        $row1 = new \stdClass();
        $row1->columns = [$col1];

        $col2 = new \stdClass();
        $col2->var_name = 'data';
        $col2->datatype = 'string';
        $col2->value_string = 'Value';
        $col2->class = null;

        $row2 = new \stdClass();
        $row2->columns = [$col2];

        $mock = $this->getMockBuilder(Key_value::class)
            ->onlyMethods(['toStdClass'])
            ->getMock();

        $stdObj = new \stdClass();
        $stdObj->rows = [$row1, $row2];
        $mock->method('toStdClass')->willReturn($stdObj);

        $html = $mock->asHTML('table', true);

        $this->assertStringContainsString('<thead>', $html);
        $this->assertStringContainsString('<th', $html);
        $this->assertStringContainsString('</tbody>', $html);
    }

    public function testAsHtmlRendersIntegerDatatype(): void
    {
        $col = new \stdClass();
        $col->var_name = 'count';
        $col->datatype = 'integer';
        $col->value_integer = 42;
        $col->class = null;

        $row = new \stdClass();
        $row->columns = [$col];

        $mock = $this->getMockBuilder(Key_value::class)
            ->onlyMethods(['toStdClass'])
            ->getMock();

        $stdObj = new \stdClass();
        $stdObj->rows = [$row];
        $mock->method('toStdClass')->willReturn($stdObj);

        $html = $mock->asHTML('table', false);

        $this->assertStringContainsString('42', $html);
    }
}
