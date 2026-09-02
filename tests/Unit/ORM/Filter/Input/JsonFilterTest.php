<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Input;

use PHPUnit\Framework\TestCase;
use Pressmind\ORM\Filter\Input\JsonFilter;

class JsonFilterTest extends TestCase
{
    public function testDecodesJsonAndNormalizesObjects(): void
    {
        $filter = new JsonFilter();

        $this->assertSame(['id' => 'ship'], $filter->filterValue('{"id":"ship"}'));
        $this->assertSame(['id' => 'ship'], $filter->filterValue((object) ['id' => 'ship']));
        $this->assertNull($filter->filterValue(null));
    }
}
