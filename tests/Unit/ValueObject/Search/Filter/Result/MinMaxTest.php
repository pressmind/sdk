<?php

namespace Pressmind\Tests\Unit\ValueObject\Search\Filter\Result;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\ValueObject\Search\Filter\Result\MinMax;

class MinMaxTest extends AbstractTestCase
{
    public function testPropertiesCanBeSetAndRead(): void
    {
        $vo = new MinMax();
        $vo->min = 100;
        $vo->max = 500;

        $this->assertSame(100, $vo->min);
        $this->assertSame(500, $vo->max);
    }

    public function testFloatValues(): void
    {
        $vo = new MinMax();
        $vo->min = 99.5;
        $vo->max = 199.99;
        $this->assertSame(99.5, $vo->min);
        $this->assertSame(199.99, $vo->max);
    }
}
