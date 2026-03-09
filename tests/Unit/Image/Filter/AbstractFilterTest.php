<?php

namespace Pressmind\Tests\Unit\Image\Filter;

use Imagick;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Tests AbstractFilter protected helpers via a concrete test double.
 */
class AbstractFilterTest extends AbstractTestCase
{
    public function testMergeParamsOverridesDefaults(): void
    {
        $filter = new ConcreteTestFilter();
        $params = ['a' => 1, 'b' => 2];
        $defaults = ['a' => 0, 'b' => 0, 'c' => 10];
        $result = $filter->publicMergeParams($params, $defaults);
        $this->assertSame(1, $result['a']);
        $this->assertSame(2, $result['b']);
        $this->assertSame(10, $result['c']);
    }

    public function testMergeParamsKeepsDefaults(): void
    {
        $filter = new ConcreteTestFilter();
        $params = [];
        $defaults = ['x' => 5, 'y' => 10];
        $result = $filter->publicMergeParams($params, $defaults);
        $this->assertSame(5, $result['x']);
        $this->assertSame(10, $result['y']);
    }

    public function testClampValueBelowMin(): void
    {
        $filter = new ConcreteTestFilter();
        $this->assertSame(1.0, $filter->publicClamp(0.5, 1.0, 10.0));
    }

    public function testClampValueAboveMax(): void
    {
        $filter = new ConcreteTestFilter();
        $this->assertSame(10.0, $filter->publicClamp(15.0, 1.0, 10.0));
    }

    public function testClampValueInRange(): void
    {
        $filter = new ConcreteTestFilter();
        $this->assertSame(5.0, $filter->publicClamp(5.0, 1.0, 10.0));
    }

    public function testPercentageOfDimension(): void
    {
        $filter = new ConcreteTestFilter();
        $this->assertSame(500, $filter->publicPercentageOf(1000, 50.0));
    }

    public function testPercentageOfZero(): void
    {
        $filter = new ConcreteTestFilter();
        $this->assertSame(0, $filter->publicPercentageOf(0, 50.0));
    }
}

/**
 * Concrete filter that exposes AbstractFilter protected methods for testing.
 */
class ConcreteTestFilter extends \Pressmind\Image\Filter\AbstractFilter
{
    public function apply(Imagick $image, array $params): Imagick
    {
        return $image;
    }

    public function getName(): string
    {
        return 'concrete_test';
    }

    public function publicMergeParams(array $params, array $defaults): array
    {
        return $this->mergeParams($params, $defaults);
    }

    public function publicClamp(float $value, float $min, float $max): float
    {
        return $this->clamp($value, $min, $max);
    }

    public function publicPercentageOf(int $dimension, float $percentage): int
    {
        return $this->percentageOf($dimension, $percentage);
    }
}
