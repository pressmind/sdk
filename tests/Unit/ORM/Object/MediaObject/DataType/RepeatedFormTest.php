<?php

namespace Pressmind\Tests\Unit\ORM\Object\MediaObject\DataType;

use Pressmind\ORM\Object\MediaObject\DataType\Repeated_form;
use Pressmind\Tests\Unit\AbstractTestCase;

class RepeatedFormTest extends AbstractTestCase
{
    public function testIconColumnRoundTripsStructuredJson(): void
    {
        $column = new Repeated_form\Row\Column();
        $column->datatype = 'icon';
        $column->value_icon = ['id' => 'ship', 'variants' => [['style' => 'solid', 'url' => 'https://example.test/ship.svg']]];
        $copy = new Repeated_form\Row\Column();
        $copy->fromJson($column->toJson());
        $this->assertSame($column->value_icon, $copy->value_icon);
        $this->assertNull($copy->value_string);
    }

    public function testAsHtmlRendersIconsWithEscapedAttributes(): void
    {
        $form = new Repeated_form();
        $form->fromJson(json_encode(['rows' => [['columns' => [
            ['datatype' => 'string', 'value_string' => '<strong>Reise</strong>'],
            ['datatype' => 'icon', 'value_icon' => ['url' => 'https://example.test/ship.svg?q="&x=1', 'name' => 'Schiff "A" <B>']],
        ]]]]));
        $html = $form->asHTML('table', false);
        $this->assertStringContainsString('<strong>Reise</strong>', $html);
        $this->assertStringContainsString('<img src="https://example.test/ship.svg?q=&quot;&amp;x=1" alt="Schiff &quot;A&quot; &lt;B&gt;">', $html);
    }

    public function testAsHtmlOmitsMissingAndUnsafeIconUrls(): void
    {
        foreach ([null, '', 'javascript:alert(1)', 'data:image/svg+xml,test', '//example.test/icon.svg'] as $url) {
            $form = new Repeated_form();
            $form->fromJson(json_encode(['rows' => [['columns' => [
                ['datatype' => 'icon', 'value_icon' => ['url' => $url, 'name' => 'Symbol']],
            ]]]]));
            $this->assertStringNotContainsString('<img', $form->asHTML('table', false));
        }
    }

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
