<?php

namespace Pressmind\Tests\Unit\Image;

use PHPUnit\Framework\TestCase;
use Pressmind\Image\Processor;

class ProcessorTest extends TestCase
{
    public function testConstructorCreatesInstance(): void
    {
        $processor = new Processor();
        $this->assertInstanceOf(Processor::class, $processor);
    }

    public function testGetLogReturnsEmptyArrayInitially(): void
    {
        $processor = new Processor();
        $this->assertSame([], $processor->getLog());
    }
}
