<?php

namespace Pressmind\Tests\Unit\ORM\Filter;

use Pressmind\ORM\Filter\Factory;
use Pressmind\ORM\Filter\FilterInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ORM Filter Factory.
 */
class FactoryTest extends TestCase
{
    public function testCreateInputStringFilter(): void
    {
        $filter = Factory::create('string', 'input');
        $this->assertInstanceOf(FilterInterface::class, $filter);
        $this->assertSame('hello', $filter->filterValue('hello'));
        $this->assertIsArray($filter->getErrors());
    }

    public function testCreateOutputStringFilter(): void
    {
        $filter = Factory::create('string', 'output');
        $this->assertInstanceOf(FilterInterface::class, $filter);
        $this->assertIsArray($filter->getErrors());
    }

    public function testCreateInputBooleanFilter(): void
    {
        $filter = Factory::create('boolean', 'input');
        $this->assertInstanceOf(FilterInterface::class, $filter);
        $this->assertTrue($filter->filterValue(1));
        $this->assertFalse($filter->filterValue(0));
    }

    public function testCreateOutputBooleanFilter(): void
    {
        $filter = Factory::create('boolean', 'output');
        $this->assertInstanceOf(FilterInterface::class, $filter);
        $this->assertSame(1, $filter->filterValue(true));
        $this->assertSame(0, $filter->filterValue(false));
    }

    public function testCreateWithParams(): void
    {
        $filter = Factory::create('string', 'input', ['some' => 'param']);
        $this->assertInstanceOf(FilterInterface::class, $filter);
    }
}
