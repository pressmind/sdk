<?php

namespace Pressmind\Tests\Unit\Image\Filter;

use Imagick;
use Pressmind\Image\Filter\FilterChain;
use Pressmind\Image\Filter\FilterInterface;
use Pressmind\Image\Filter\GrayscaleFilter;
use Pressmind\Tests\Unit\AbstractTestCase;

class FilterChainTest extends AbstractTestCase
{
    public function testAddFilterReturnsSelf(): void
    {
        $chain = new FilterChain();
        $filter = new GrayscaleFilter();
        $this->assertSame($chain, $chain->addFilter($filter));
        $this->assertSame($chain, $chain->addFilter($filter, ['x' => 1]));
    }

    public function testCountReturnsZeroInitially(): void
    {
        $chain = new FilterChain();
        $this->assertSame(0, $chain->count());
    }

    public function testCountAfterAddingFilters(): void
    {
        $chain = new FilterChain();
        $chain->addFilter(new GrayscaleFilter());
        $this->assertSame(1, $chain->count());
        $chain->addFilter(new GrayscaleFilter());
        $this->assertSame(2, $chain->count());
    }

    /**
     * @requires extension imagick
     */
    public function testProcessAppliesFiltersInOrder(): void
    {
        $order = [];
        $filter1 = new class($order, 1) implements FilterInterface {
            private $order;
            private $id;
            public function __construct(array &$order, int $id) {
                $this->order = &$order;
                $this->id = $id;
            }
            public function apply(Imagick $image, array $params): Imagick { $this->order[] = $this->id; return $image; }
            public function getName(): string { return 'mock1'; }
        };
        $filter2 = new class($order, 2) implements FilterInterface {
            private $order;
            private $id;
            public function __construct(array &$order, int $id) {
                $this->order = &$order;
                $this->id = $id;
            }
            public function apply(Imagick $image, array $params): Imagick { $this->order[] = $this->id; return $image; }
            public function getName(): string { return 'mock2'; }
        };
        $chain = new FilterChain();
        $chain->addFilter($filter1)->addFilter($filter2);
        $image = new Imagick();
        $image->newPseudoImage(10, 10, 'canvas:white');
        $chain->process($image);
        $image->destroy();
        $this->assertSame([1, 2], $order);
    }

    public function testCreateFromConfigWithValidFilters(): void
    {
        $config = [
            ['class' => GrayscaleFilter::class, 'params' => []],
        ];
        $chain = FilterChain::createFromConfig($config);
        $this->assertInstanceOf(FilterChain::class, $chain);
        $this->assertSame(1, $chain->count());
    }

    public function testCreateFromConfigSkipsEmptyClass(): void
    {
        $config = [
            ['class' => '', 'params' => []],
            ['class' => GrayscaleFilter::class, 'params' => []],
        ];
        $chain = FilterChain::createFromConfig($config);
        $this->assertSame(1, $chain->count());
    }

    public function testCreateFromConfigSkipsNonExistentClass(): void
    {
        $config = [
            ['class' => 'NonExistentFilterClass', 'params' => []],
            ['class' => GrayscaleFilter::class, 'params' => []],
        ];
        $chain = FilterChain::createFromConfig($config);
        $this->assertSame(1, $chain->count());
    }

    public function testCreateFromConfigSkipsNonFilterClass(): void
    {
        $config = [
            ['class' => \stdClass::class, 'params' => []],
            ['class' => GrayscaleFilter::class, 'params' => []],
        ];
        $chain = FilterChain::createFromConfig($config);
        $this->assertSame(1, $chain->count());
    }

    public function testCreateFromConfigUsesDefaultParams(): void
    {
        $config = [
            ['class' => GrayscaleFilter::class, 'params' => ['custom' => 42]],
        ];
        $chain = FilterChain::createFromConfig($config);
        $this->assertSame(1, $chain->count());
    }
}
