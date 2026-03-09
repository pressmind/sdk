<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Input;

use Pressmind\ORM\Filter\Input\BooleanFilter;
use PHPUnit\Framework\TestCase;

class BooleanFilterTest extends TestCase
{
    public function testFilterValueBoolval(): void
    {
        $filter = new BooleanFilter();
        $this->assertTrue($filter->filterValue(1));
        $this->assertTrue($filter->filterValue('1'));
        $this->assertFalse($filter->filterValue(0));
        $this->assertFalse($filter->filterValue(''));
    }

    public function testGetErrorsReturnsArray(): void
    {
        $filter = new BooleanFilter();
        $this->assertIsArray($filter->getErrors());
    }
}
