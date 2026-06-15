<?php

namespace Pressmind\Tests\Unit\ORM\Object\MediaObject\DataType;

use Pressmind\ORM\Object\MediaObject\DataType\Repeated_form;
use Pressmind\Tests\Unit\AbstractTestCase;

class RepeatedFormTest extends AbstractTestCase
{
    public function testAsHtmlReturnsTableWithTextRows(): void
    {
        $col1 = new \stdClass();
        $col1->var_name = 'headline';
        $col1->datatype = 'string';
        $col1->value_string = 'Tag 1';
        $col1->class = null;

        $col2 = new \stdClass();
        $col2->var_name = 'description';
        $col2->datatype = 'string';
        $col2->value_string = 'Anreise';
        $col2->class = null;

        $row = new \stdClass();
        $row->columns = [$col1, $col2];

        $mock = $this->getMockBuilder(Repeated_form::class)
            ->onlyMethods(['toStdClass'])
            ->getMock();

        $stdObj = new \stdClass();
        $stdObj->rows = [$row];
        $mock->method('toStdClass')->willReturn($stdObj);

        $html = $mock->asHTML('table', false);

        $this->assertStringContainsString('<table class="table">', $html);
        $this->assertStringNotContainsString('/><tbody>', $html);
        $this->assertStringContainsString('Tag 1', $html);
        $this->assertStringContainsString('Anreise', $html);
    }

    public function testAsHtmlReturnsNullWhenNoRows(): void
    {
        $mock = $this->getMockBuilder(Repeated_form::class)
            ->onlyMethods(['toStdClass'])
            ->getMock();

        $stdObj = new \stdClass();
        $stdObj->rows = [];
        $mock->method('toStdClass')->willReturn($stdObj);

        $this->assertNull($mock->asHTML());
    }

    public function testRowAcceptsValidityDates(): void
    {
        $row = new Repeated_form\Row();
        $row->sort = 1;
        $row->valid_from = '2026-06-15';
        $row->valid_to = '2029-05-15';

        $stdClass = $row->toStdClass(false);

        $this->assertSame('2026-06-15 00:00:00', $stdClass->valid_from->format('Y-m-d H:i:s'));
        $this->assertSame('2029-05-15 00:00:00', $stdClass->valid_to->format('Y-m-d H:i:s'));
    }
}
