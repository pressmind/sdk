<?php

namespace Pressmind\Tests\Unit\Search;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Paginator;

class PaginatorTest extends TestCase
{
    public function testConstructorWithPageSizeOnlyDefaultsCurrentPageToOne(): void
    {
        $paginator = new Paginator(10);
        $this->assertSame(1, $paginator->getCurrentPage());
    }

    public function testConstructorWithPageSizeAndCurrentPage(): void
    {
        $paginator = new Paginator(10, 5);
        $this->assertSame(5, $paginator->getCurrentPage());
    }

    public function testGetPageSizeReturnsCorrectValue(): void
    {
        $paginator = new Paginator(25);
        $this->assertSame(25, $paginator->getPageSize());
    }

    public function testGetCurrentPageReturnsCorrectValue(): void
    {
        $paginator = new Paginator(10, 3);
        $this->assertSame(3, $paginator->getCurrentPage());
    }

    public function testGetTotalPagesReturnsNullBeforeGetLimits(): void
    {
        $paginator = new Paginator(10);
        $this->assertNull($paginator->getTotalPages());
    }

    public function testGetLimitsCalculatesCorrectValuesForPageOne(): void
    {
        $paginator = new Paginator(10, 1);
        $limits = $paginator->getLimits(50);

        $this->assertSame(0, $limits['start']);
        $this->assertSame(10, $limits['length']);
    }

    public function testGetLimitsCalculatesCorrectValuesForPageThree(): void
    {
        $paginator = new Paginator(10, 3);
        $limits = $paginator->getLimits(50);

        $this->assertSame(20, $limits['start']);
        $this->assertSame(10, $limits['length']);
    }

    public function testGetLimitsSetsTotalPagesCorrectly(): void
    {
        $paginator = new Paginator(10, 1);
        $paginator->getLimits(55);

        $this->assertSame(6.0, $paginator->getTotalPages());
    }

    public function testGetLimitsWithZeroTotal(): void
    {
        $paginator = new Paginator(10, 1);
        $limits = $paginator->getLimits(0);

        $this->assertSame(0, $limits['start']);
        $this->assertSame(10, $limits['length']);
        $this->assertSame(0.0, $paginator->getTotalPages());
    }

    public function testGetLimitsWithTotalLessThanPageSize(): void
    {
        $paginator = new Paginator(10, 1);
        $limits = $paginator->getLimits(3);

        $this->assertSame(0, $limits['start']);
        $this->assertSame(10, $limits['length']);
        $this->assertSame(1.0, $paginator->getTotalPages());
    }

    public function testStaticCreateFactoryMethod(): void
    {
        $paginator = Paginator::create(15);

        $this->assertInstanceOf(Paginator::class, $paginator);
        $this->assertSame(15, $paginator->getPageSize());
        $this->assertSame(1, $paginator->getCurrentPage());
    }

    public function testStaticCreateWithCurrentPage(): void
    {
        $paginator = Paginator::create(15, 4);

        $this->assertInstanceOf(Paginator::class, $paginator);
        $this->assertSame(15, $paginator->getPageSize());
        $this->assertSame(4, $paginator->getCurrentPage());
    }
}
