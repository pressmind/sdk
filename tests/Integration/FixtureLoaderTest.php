<?php

namespace Pressmind\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Tests for FixtureLoader (date resolution and fixture loading).
 * Does not require database.
 */
class FixtureLoaderTest extends TestCase
{
    public function testResolveDate(): void
    {
        $date = FixtureLoader::resolveDate(30);
        $this->assertInstanceOf(\DateTime::class, $date);
        $now = new \DateTime();
        $diff = $now->diff($date);
        $this->assertGreaterThanOrEqual(29, (int) $diff->days);
        $this->assertLessThanOrEqual(31, (int) $diff->days);
    }

    public function testLoadCheapestPriceFixtureResolvesOffsets(): void
    {
        $rows = FixtureLoader::loadCheapestPriceFixture('scenario_1_pauschalreise', 'touristic');
        $this->assertNotEmpty($rows);
        $first = $rows[0];
        $this->assertArrayHasKey('date_departure', $first);
        $this->assertArrayNotHasKey('date_departure_offset', $first);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $first['date_departure']);
    }

    public function testLoadCheapestPriceFixturePreservesNonDateFields(): void
    {
        $rows = FixtureLoader::loadCheapestPriceFixture('scenario_1_pauschalreise', 'touristic');
        $first = $rows[0];
        $this->assertArrayHasKey('price_total', $first);
        $this->assertSame(899.0, $first['price_total']);
        $this->assertArrayHasKey('transport_type', $first);
        $this->assertSame('BUS', $first['transport_type']);
    }
}
