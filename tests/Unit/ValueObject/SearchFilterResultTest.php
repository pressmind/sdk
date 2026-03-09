<?php

namespace Pressmind\Tests\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Pressmind\ValueObject\MediaObject\Result\GetByPrettyUrl;
use Pressmind\ValueObject\MediaObject\Result\GetPrettyUrls;
use Pressmind\ValueObject\Search\Filter\Result\DateRange;
use Pressmind\ValueObject\Search\Filter\Result\MinMax;

class SearchFilterResultTest extends TestCase
{
    public function testDateRangeCanBeInstantiated(): void
    {
        $dateRange = new DateRange();
        $this->assertInstanceOf(DateRange::class, $dateRange);
    }

    public function testDateRangePropertiesAreAccessible(): void
    {
        $dateRange = new DateRange();
        $dateRange->from = new \DateTime('2026-01-01');
        $dateRange->to = new \DateTime('2026-12-31');

        $this->assertInstanceOf(\DateTime::class, $dateRange->from);
        $this->assertInstanceOf(\DateTime::class, $dateRange->to);
        $this->assertSame('2026-01-01', $dateRange->from->format('Y-m-d'));
        $this->assertSame('2026-12-31', $dateRange->to->format('Y-m-d'));
    }

    public function testDateRangeDefaultsToNull(): void
    {
        $dateRange = new DateRange();
        $this->assertNull($dateRange->from);
        $this->assertNull($dateRange->to);
    }

    public function testMinMaxCanBeInstantiated(): void
    {
        $minMax = new MinMax();
        $this->assertInstanceOf(MinMax::class, $minMax);
    }

    public function testMinMaxPropertiesAreAccessible(): void
    {
        $minMax = new MinMax();
        $minMax->min = 100;
        $minMax->max = 9999;

        $this->assertSame(100, $minMax->min);
        $this->assertSame(9999, $minMax->max);
    }

    public function testMinMaxSupportsFloats(): void
    {
        $minMax = new MinMax();
        $minMax->min = 49.99;
        $minMax->max = 1299.50;

        $this->assertSame(49.99, $minMax->min);
        $this->assertSame(1299.50, $minMax->max);
    }

    public function testMinMaxDefaultsToNull(): void
    {
        $minMax = new MinMax();
        $this->assertNull($minMax->min);
        $this->assertNull($minMax->max);
    }

    public function testGetByPrettyUrlCanBeInstantiated(): void
    {
        $result = new GetByPrettyUrl();
        $this->assertInstanceOf(GetByPrettyUrl::class, $result);
    }

    public function testGetByPrettyUrlPropertiesAreAccessible(): void
    {
        $result = new GetByPrettyUrl();
        $result->id = 42;
        $result->id_object_type = 5;
        $result->visibility = 30;
        $result->language = 'de';

        $this->assertSame(42, $result->id);
        $this->assertSame(5, $result->id_object_type);
        $this->assertSame(30, $result->visibility);
        $this->assertSame('de', $result->language);
    }

    public function testGetByPrettyUrlDefaultsToNull(): void
    {
        $result = new GetByPrettyUrl();
        $this->assertNull($result->id);
        $this->assertNull($result->id_object_type);
        $this->assertNull($result->visibility);
        $this->assertNull($result->language);
    }

    public function testGetPrettyUrlsCanBeInstantiated(): void
    {
        $result = new GetPrettyUrls();
        $this->assertInstanceOf(GetPrettyUrls::class, $result);
    }

    public function testGetPrettyUrlsPropertiesAreAccessible(): void
    {
        $result = new GetPrettyUrls();
        $result->id = 1;
        $result->id_media_object = 100;
        $result->id_object_type = 5;
        $result->route = '/reise/sommerurlaub';
        $result->language = 'de';
        $result->is_default = true;

        $this->assertSame(1, $result->id);
        $this->assertSame(100, $result->id_media_object);
        $this->assertSame(5, $result->id_object_type);
        $this->assertSame('/reise/sommerurlaub', $result->route);
        $this->assertSame('de', $result->language);
        $this->assertTrue($result->is_default);
    }

    public function testGetPrettyUrlsDefaultsToNull(): void
    {
        $result = new GetPrettyUrls();
        $this->assertNull($result->id);
        $this->assertNull($result->id_media_object);
        $this->assertNull($result->id_object_type);
        $this->assertNull($result->route);
        $this->assertNull($result->language);
        $this->assertNull($result->is_default);
    }
}
