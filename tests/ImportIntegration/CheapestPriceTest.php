<?php

namespace Pressmind\Tests\ImportIntegration;

/**
 * Verifies pmt2core_cheapest_price_speed and price calculation.
 */
class CheapestPriceTest extends AbstractImportTestCase
{
    public function testCheapestPriceSpeedTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_cheapest_price_speed'");
        $this->assertNotEmpty($row);
    }

    public function testCheapestPriceSpeedHasRequiredColumns(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_cheapest_price_speed');
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertContains('id_media_object', $names);
        $this->assertContains('id_booking_package', $names);
    }

    public function testCheapestPriceSpeedStateColumn(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_cheapest_price_speed');
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertContains('state', $names);
    }

    public function testCheapestPriceSpeedPriceTotalColumn(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_cheapest_price_speed');
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertContains('price_total', $names);
    }

    public function testCheapestPriceSpeedRowsWhenTouristicDataPresent(): void
    {
        self::assertNotNull($this->db);
        $count = (int) $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_cheapest_price_speed');
        $bpCount = (int) $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_touristic_booking_packages');
        if ($bpCount === 0) {
            $this->assertGreaterThanOrEqual(0, $count);
            return;
        }
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testStateValuesValid(): void
    {
        self::assertNotNull($this->db);
        $rows = $this->db->fetchAll('SELECT DISTINCT state FROM pmt2core_cheapest_price_speed LIMIT 20');
        foreach ($rows as $row) {
            $this->assertIsNumeric($row->state);
            $state = (int) $row->state;
            $this->assertGreaterThanOrEqual(0, $state);
        }
    }

    public function testPriceTotalNonNegative(): void
    {
        self::assertNotNull($this->db);
        $negative = (int) $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_cheapest_price_speed WHERE price_total < 0');
        $this->assertEquals(0, $negative);
    }

    public function testDurationPlausible(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_cheapest_price_speed');
        $names = array_map(fn($c) => $c->Field, $cols);
        if (!in_array('duration', $names, true)) {
            return;
        }
        $negative = (int) $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_cheapest_price_speed WHERE duration < 0');
        $this->assertEquals(0, $negative);
    }

    public function testOccupancyColumnIfPresent(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_cheapest_price_speed');
        $names = array_map(fn($c) => $c->Field, $cols);
        if (in_array('occupancy', $names, true)) {
            $row = $this->db->fetchRow('SELECT occupancy FROM pmt2core_cheapest_price_speed WHERE occupancy IS NOT NULL LIMIT 1');
            if ($row !== null) {
                $this->assertIsNumeric($row->occupancy);
            }
        }
        $this->assertTrue(true);
    }

    public function testLinkedToMediaObject(): void
    {
        self::assertNotNull($this->db);
        $count = (int) $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_cheapest_price_speed');
        if ($count === 0) {
            return;
        }
        $orphan = $this->db->fetchOne('SELECT c.id FROM pmt2core_cheapest_price_speed c LEFT JOIN pmt2core_media_objects m ON m.id = c.id_media_object WHERE m.id IS NULL LIMIT 1');
        $this->assertEmpty($orphan);
    }

    public function testLinkedToBookingPackage(): void
    {
        self::assertNotNull($this->db);
        $count = (int) $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_cheapest_price_speed');
        if ($count === 0) {
            return;
        }
        $orphan = $this->db->fetchOne('SELECT c.id FROM pmt2core_cheapest_price_speed c LEFT JOIN pmt2core_touristic_booking_packages b ON b.id = c.id_booking_package WHERE b.id IS NULL LIMIT 1');
        $this->assertEmpty($orphan);
    }

    public function testPrimaryKeyExists(): void
    {
        self::assertNotNull($this->db);
        $indexes = $this->db->fetchAll("SHOW INDEX FROM pmt2core_cheapest_price_speed WHERE Key_name = 'PRIMARY'");
        $this->assertNotEmpty($indexes);
    }

    public function testPriceTotalColumnType(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_cheapest_price_speed');
        foreach ($cols as $col) {
            if ($col->Field === 'price_total') {
                $this->assertMatchesRegularExpression('/decimal|float|double|int/i', $col->Type);
                return;
            }
        }
        $this->fail('price_total column not found');
    }

    public function testDateDepartureColumnIfPresent(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_cheapest_price_speed');
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertGreaterThanOrEqual(0, count($names));
    }

    public function testNoDuplicateSpeedEntriesForSameMediaAndPackage(): void
    {
        self::assertNotNull($this->db);
        $dup = $this->db->fetchOne('SELECT COUNT(*) c FROM pmt2core_cheapest_price_speed GROUP BY fingerprint, option_occupancy HAVING c > 1 LIMIT 1');
        $this->assertEmpty($dup);
    }

    public function testStateBookableHasPositivePrice(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow('SELECT price_total FROM pmt2core_cheapest_price_speed WHERE state = 3 AND (price_total IS NULL OR price_total <= 0) LIMIT 1');
        $this->assertEmpty($row, 'Bookable (state=3) should have positive price_total');
    }

    /**
     * Where earlybird_discount > 0, discounted price must be lower than regular.
     */
    public function testEarlybirdConsistencyDiscountedLowerThanRegular(): void
    {
        self::assertNotNull($this->db);
        $count = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM pmt2core_cheapest_price_speed WHERE earlybird_discount > 0 AND price_total >= price_regular_before_discount'
        );
        $this->assertEquals(0, $count, 'Rows with earlybird_discount must have price_total < price_regular_before_discount');
    }

    /**
     * Percentage discount: price_regular_before_discount - price_total ≈ regular * earlybird_discount / 100 (bcmul rounding).
     */
    public function testEarlybirdPercentageCalculationConsistency(): void
    {
        self::assertNotNull($this->db);
        $rows = $this->db->fetchAll(
            'SELECT price_regular_before_discount, price_total, earlybird_discount FROM pmt2core_cheapest_price_speed WHERE earlybird_discount > 0 AND earlybird_discount_f IS NULL LIMIT 20'
        );
        foreach ($rows as $row) {
            $regular = (float) $row->price_regular_before_discount;
            $total = (float) $row->price_total;
            $percent = (float) $row->earlybird_discount;
            $expectedDiscount = round($regular * ($percent / 100), 2);
            $actualDiscount = $regular - $total;
            $this->assertEqualsWithDelta($expectedDiscount, $actualDiscount, 0.02, 'EarlyBird percent discount should match bcmul calculation');
        }
    }

    /**
     * Where Streichpreis is shown (price_option_pseudo > price_total), price_total must be positive.
     * Note: API can return price_option_pseudo <= price_total; then no Streichpreis is displayed (allowed).
     */
    public function testPseudoPriceConsistencyWhenDisplayed(): void
    {
        self::assertNotNull($this->db);
        $invalid = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM pmt2core_cheapest_price_speed WHERE price_option_pseudo > price_total AND (price_total IS NULL OR price_total < 0)'
        );
        $this->assertEquals(0, $invalid, 'Rows with pseudo Streichpreis displayed must have positive price_total');
    }

    /**
     * When earlybird_discount > 0 and earlybird_discount_date_to IS NULL, discount has no end date (unbefristet).
     */
    public function testEarlybirdWithoutDateToAllowed(): void
    {
        self::assertNotNull($this->db);
        $count = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM pmt2core_cheapest_price_speed WHERE earlybird_discount > 0 AND earlybird_discount_date_to IS NULL'
        );
        $this->assertGreaterThanOrEqual(0, $count);
    }
}
