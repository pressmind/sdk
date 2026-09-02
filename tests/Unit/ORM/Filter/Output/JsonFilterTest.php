<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Output;

use PHPUnit\Framework\TestCase;
use Pressmind\ORM\Filter\Output\JsonFilter;

class JsonFilterTest extends TestCase
{
    public function testEncodesStructuredValues(): void
    {
        $filter = new JsonFilter();

        $this->assertSame('{"id":"ship"}', $filter->filterValue(['id' => 'ship']));
        $this->assertSame('{"id":"ship"}', $filter->filterValue((object) ['id' => 'ship']));
        $this->assertNull($filter->filterValue(null));
    }
}
