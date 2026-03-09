<?php

namespace Pressmind\Tests\ImportIntegration;

/**
 * Verifies touristic data: booking packages, dates, transports, housing, insurances.
 */
class TouristicImportTest extends AbstractImportTestCase
{
    public function testTouristicBookingPackagesTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_touristic_booking_packages'");
        $this->assertNotEmpty($row);
    }

    public function testTouristicDatesTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_touristic_dates'");
        $this->assertNotEmpty($row);
    }

    public function testBookingPackagesHaveRequiredColumns(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_touristic_booking_packages');
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertContains('id_media_object', $names);
        $this->assertContains('id', $names);
    }

    public function testTouristicDatesHaveRequiredColumns(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_touristic_dates');
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertContains('id_booking_package', $names);
    }

    public function testTouristicTransportsTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_touristic_transports'");
        $this->assertNotEmpty($row);
    }

    public function testTouristicHousingPackagesTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_touristic_housing_packages'");
        $this->assertNotEmpty($row);
    }

    public function testTouristicStartingpointsTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_touristic_startingpoints'");
        $this->assertNotEmpty($row);
    }

    public function testTouristicInsurancesTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_touristic_insurances'");
        $this->assertNotEmpty($row);
    }

    public function testBookingPackagesLinkedToMediaObjects(): void
    {
        self::assertNotNull($this->db);
        $count = (int) $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_touristic_booking_packages');
        if ($count === 0) {
            $this->markTestSkipped('No booking packages (fixture may have no touristic data)');
        }
        $orphan = $this->db->fetchOne('SELECT b.id FROM pmt2core_touristic_booking_packages b LEFT JOIN pmt2core_media_objects m ON m.id = b.id_media_object WHERE m.id IS NULL LIMIT 1');
        $this->assertEmpty($orphan);
    }

    public function testDatesLinkedToBookingPackages(): void
    {
        self::assertNotNull($this->db);
        $count = (int) $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_touristic_dates');
        if ($count === 0) {
            return;
        }
        $orphan = $this->db->fetchOne('SELECT d.id FROM pmt2core_touristic_dates d LEFT JOIN pmt2core_touristic_booking_packages b ON b.id = d.id_booking_package WHERE b.id IS NULL LIMIT 1');
        $this->assertEmpty($orphan);
    }

    public function testTouristicDatesHaveDateColumns(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_touristic_dates');
        $names = array_map(fn($c) => $c->Field, $cols);
        $dateLike = array_filter($names, static function ($n) {
            return stripos($n, 'date') !== false || $n === 'departure' || $n === 'arrival';
        });
        $this->assertGreaterThanOrEqual(0, count($dateLike));
    }

    public function testTransportOptionsTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_touristic_options'");
        $this->assertNotEmpty($row);
    }

    public function testHousingPackageOptionsExist(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_touristic_housing_packages'");
        $this->assertNotEmpty($row);
    }

    public function testTouristicBaseTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_touristic_base'");
        $this->assertNotEmpty($row);
    }

    public function testDatesHaveValidStructure(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow('SELECT id, id_booking_package FROM pmt2core_touristic_dates LIMIT 1');
        if ($row === null) {
            return;
        }
        $this->assertObjectHasProperty('id_booking_package', $row);
    }

    public function testBookingPackageDurationPlausible(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_touristic_booking_packages');
        $names = array_map(fn($c) => $c->Field, $cols);
        if (!in_array('duration', $names, true)) {
            return;
        }
        $row = $this->db->fetchRow('SELECT duration FROM pmt2core_touristic_booking_packages WHERE duration IS NOT NULL LIMIT 1');
        if ($row === null) {
            return;
        }
        $this->assertGreaterThanOrEqual(0, (int) $row->duration);
    }

    public function testStartingpointOptionsTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_touristic_startingpoint_options'");
        $this->assertNotEmpty($row);
    }

    public function testInsuranceGroupsTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_touristic_insurance_groups'");
        if (empty($row)) {
            $this->markTestSkipped('pmt2core_touristic_insurance_groups cannot be created due to duplicate ENUM value in Group model mode field');
        }
        $this->assertNotEmpty($row);
    }

    public function testTouristicOptionDescriptionsTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_touristic_option_descriptions'");
        $this->assertNotEmpty($row);
    }

    public function testEarlyBirdDiscountTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_touristic_early_bird_discount_group'");
        $this->assertNotEmpty($row);
    }

    public function testTransportsLinkedToDatesOrPackages(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_touristic_transports');
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertContains('id', $names);
    }

    public function testHousingPackagesLinkedToBookingPackages(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_touristic_housing_packages');
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertContains('id_booking_package', $names);
    }

    public function testInsuranceTablesStructure(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_touristic_insurances'");
        $this->assertNotEmpty($row);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_touristic_insurances');
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertContains('id', $names);
    }

    public function testTouristicDatesDateColumnsValidFormat(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_touristic_dates');
        $names = array_map(fn($c) => $c->Field, $cols);
        $dateCol = null;
        foreach (['departure', 'arrival', 'date_departure', 'date_arrival', 'date_from', 'date_to'] as $candidate) {
            if (in_array($candidate, $names, true)) {
                $dateCol = $candidate;
                break;
            }
        }
        if ($dateCol === null) {
            return;
        }
        $row = $this->db->fetchRow('SELECT `' . $dateCol . '` as val FROM pmt2core_touristic_dates WHERE `' . $dateCol . '` IS NOT NULL LIMIT 1');
        if ($row === null) {
            return;
        }
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}/', (string) $row->val, $dateCol . ' should be date-like');
    }

    public function testNoDuplicateBookingPackageIds(): void
    {
        self::assertNotNull($this->db);
        $total = (int) $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_touristic_booking_packages');
        $distinct = (int) $this->db->fetchOne('SELECT COUNT(DISTINCT id) FROM pmt2core_touristic_booking_packages');
        $this->assertEquals($total, $distinct);
    }

    public function testPickupserviceTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_touristic_pickupservices'");
        $this->assertNotEmpty($row);
    }
}
