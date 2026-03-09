<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Output;

use Pressmind\ORM\Filter\Output\LongtextFilter;
use PHPUnit\Framework\TestCase;

class LongtextFilterTest extends TestCase
{
    private LongtextFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new LongtextFilter();
    }

    public function testFilterValueReturnsString(): void
    {
        $this->assertSame('some long text content', $this->filter->filterValue('some long text content'));
    }

    public function testFilterValueEmptyStringReturnsNull(): void
    {
        $this->assertNull($this->filter->filterValue(''));
    }

    public function testFilterValueNullReturnsNull(): void
    {
        $this->assertNull($this->filter->filterValue(null));
    }

    public function testFilterValueNullStringReturnsNull(): void
    {
        $this->assertNull($this->filter->filterValue('null'));
    }

    public function testFilterValueObjectReturnsErrorString(): void
    {
        $this->assertSame('Error: Object to string conversion', $this->filter->filterValue(new \stdClass()));
    }

    public function testFilterValueReturnsHtmlContent(): void
    {
        $html = '<p>Hello <strong>World</strong></p>';
        $this->assertSame($html, $this->filter->filterValue($html));
    }

    public function testFilterValueReturnsNumericString(): void
    {
        $this->assertSame('12345', $this->filter->filterValue('12345'));
    }

    public function testGetErrorsReturnsEmptyArray(): void
    {
        $this->assertIsArray($this->filter->getErrors());
        $this->assertEmpty($this->filter->getErrors());
    }
}
