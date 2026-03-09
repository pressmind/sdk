<?php

namespace Pressmind\Tests\Integration\System;

use Pressmind\DB\Scaffolder\Mysql as ScaffolderMysql;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\Registry;
use Pressmind\System\TouristicOrphans;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for TouristicOrphans.
 * Tests orphan detection, statistics and detail diagnosis against real MySQL.
 */
class TouristicOrphansIntegrationTest extends AbstractIntegrationTestCase
{
    private const OBJ_TYPE_ID = 9998;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }

        $this->ensureTables();
        $this->cleanTestData();
        $this->setConfigWithMediaTypes();
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            $this->cleanTestData();
            $this->dropMinimalTables();
        }
        parent::tearDown();
    }

    private function setConfigWithMediaTypes(): void
    {
        $config = $this->getIntegrationConfig();
        $config['data'] = array_merge($config['data'] ?? [], [
            'primary_media_type_ids' => [self::OBJ_TYPE_ID],
            'media_types' => [
                self::OBJ_TYPE_ID => 'TestType',
            ],
        ]);
        Registry::getInstance()->add('config', $config);
    }

    private function ensureTables(): void
    {
        try {
            $scaffolder = new ScaffolderMysql(new MediaObject());
            $scaffolder->run(true);
        } catch (\Throwable $e) {
            // table may already exist
        }

        $tables = [
            'pmt2core_cheapest_price_speed' => '
                CREATE TABLE IF NOT EXISTS pmt2core_cheapest_price_speed (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    id_media_object INT,
                    fingerprint VARCHAR(255)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
            'pmt2core_touristic_booking_packages' => '
                CREATE TABLE IF NOT EXISTS pmt2core_touristic_booking_packages (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    id_media_object INT,
                    name VARCHAR(255),
                    duration INT DEFAULT 0,
                    ibe_type VARCHAR(50) DEFAULT NULL,
                    product_type_ibe VARCHAR(50) DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
            'pmt2core_touristic_dates' => '
                CREATE TABLE IF NOT EXISTS pmt2core_touristic_dates (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    id_booking_package BIGINT,
                    departure DATETIME,
                    arrival DATETIME,
                    state INT DEFAULT 1,
                    id_media_object INT DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
            'pmt2core_touristic_options' => '
                CREATE TABLE IF NOT EXISTS pmt2core_touristic_options (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    id_media_object INT,
                    id_booking_package BIGINT DEFAULT NULL,
                    name VARCHAR(255),
                    code VARCHAR(100) DEFAULT NULL,
                    type VARCHAR(50) DEFAULT NULL,
                    price DECIMAL(10,2) DEFAULT 0
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
        ];

        foreach ($tables as $sql) {
            try {
                $this->db->execute($sql);
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    private function cleanTestData(): void
    {
        $this->db->execute('DELETE FROM pmt2core_media_objects WHERE id_object_type = ?', [self::OBJ_TYPE_ID]);
        $this->db->execute('DELETE FROM pmt2core_cheapest_price_speed WHERE id_media_object >= 90000');
        $this->db->execute('DELETE FROM pmt2core_touristic_booking_packages WHERE id_media_object >= 90000');
        $this->db->execute('DELETE FROM pmt2core_touristic_dates WHERE id_booking_package >= 90000');
        $this->db->execute('DELETE FROM pmt2core_touristic_options WHERE id_media_object >= 90000');
    }

    private function dropMinimalTables(): void
    {
        foreach (['pmt2core_cheapest_price_speed', 'pmt2core_touristic_booking_packages', 'pmt2core_touristic_dates', 'pmt2core_touristic_options'] as $table) {
            try {
                $this->db->execute('DROP TABLE IF EXISTS `' . $table . '`');
            } catch (\Throwable $e) {
            }
        }
    }

    private function insertMediaObject(int $id, string $name, int $visibility = 30): void
    {
        $this->db->execute(
            'INSERT INTO pmt2core_media_objects (id, id_pool, id_object_type, id_client, id_brand, id_season, name, code, visibility, state, hidden) VALUES (?, 1, ?, 1, 1, 1, ?, ?, ?, 1, 0)',
            [$id, self::OBJ_TYPE_ID, $name, 'CODE-' . $id, $visibility]
        );
    }

    private function insertCheapestPrice(int $idMediaObject): void
    {
        $this->db->execute(
            'INSERT INTO pmt2core_cheapest_price_speed (id_media_object, fingerprint) VALUES (?, ?)',
            [$idMediaObject, 'fp-' . $idMediaObject]
        );
    }

    private function insertBookingPackage(int $id, int $idMediaObject): void
    {
        $this->db->execute(
            'INSERT INTO pmt2core_touristic_booking_packages (id, id_media_object, name) VALUES (?, ?, ?)',
            [$id, $idMediaObject, 'BP-' . $id]
        );
    }

    private function insertDate(int $idBookingPackage, string $departure, string $arrival): void
    {
        $this->db->execute(
            'INSERT INTO pmt2core_touristic_dates (id_booking_package, departure, arrival, state) VALUES (?, ?, ?, 1)',
            [$idBookingPackage, $departure, $arrival]
        );
    }

    private function insertOption(int $idMediaObject, ?int $idBookingPackage, string $name, float $price): void
    {
        $this->db->execute(
            'INSERT INTO pmt2core_touristic_options (id_media_object, id_booking_package, name, price) VALUES (?, ?, ?, ?)',
            [$idMediaObject, $idBookingPackage, $name, $price]
        );
    }

    // --- findOrphans ---

    public function testFindOrphansEmptyDatabase(): void
    {
        $orphans = new TouristicOrphans();
        $result = $orphans->findOrphans([self::OBJ_TYPE_ID]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFindOrphansWithEmptyObjectTypes(): void
    {
        $orphans = new TouristicOrphans();
        $result = $orphans->findOrphans([]);

        $this->assertSame([], $result);
    }

    public function testFindOrphansNoOrphansWhenAllHavePrices(): void
    {
        $this->insertMediaObject(90001, 'Product A');
        $this->insertCheapestPrice(90001);

        $orphans = new TouristicOrphans();
        $result = $orphans->findOrphans([self::OBJ_TYPE_ID]);

        $this->assertEmpty($result);
    }

    public function testFindOrphansDetectsOrphan(): void
    {
        $this->insertMediaObject(90001, 'Product A');
        $this->insertMediaObject(90002, 'Orphan B');
        $this->insertCheapestPrice(90001);

        $orphans = new TouristicOrphans();
        $result = $orphans->findOrphans([self::OBJ_TYPE_ID]);

        $this->assertCount(1, $result);
        $this->assertEquals(90002, $result[0]->id);
        $this->assertEquals('Orphan B', $result[0]->name);
    }

    public function testFindOrphansIncludesCounts(): void
    {
        $this->insertMediaObject(90003, 'Orphan with BP');
        $this->insertBookingPackage(90003, 90003);
        $this->insertDate(90003, '2027-06-01', '2027-06-08');
        $this->insertOption(90003, 90003, 'Option A', 199.00);

        $orphans = new TouristicOrphans();
        $result = $orphans->findOrphans([self::OBJ_TYPE_ID]);

        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]->booking_packages_count);
        $this->assertEquals(1, $result[0]->dates_count);
        $this->assertEquals(1, $result[0]->options_count);
    }

    public function testFindOrphansRespectsVisibility(): void
    {
        $this->insertMediaObject(90004, 'Hidden product', 10);

        $orphans = new TouristicOrphans();
        $resultDefault = $orphans->findOrphans([self::OBJ_TYPE_ID], 30);
        $this->assertEmpty($resultDefault);

        $resultCustom = $orphans->findOrphans([self::OBJ_TYPE_ID], 10);
        $this->assertCount(1, $resultCustom);
    }

    // --- getStatistics ---

    public function testGetStatisticsEmptyObjectTypes(): void
    {
        $orphans = new TouristicOrphans();
        $result = $orphans->getStatistics([]);

        $this->assertSame([], $result);
    }

    public function testGetStatisticsEmptyDatabase(): void
    {
        $orphans = new TouristicOrphans();
        $result = $orphans->getStatistics([self::OBJ_TYPE_ID]);

        $this->assertArrayHasKey('by_object_type', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertSame(0, $result['total']['visible_count']);
        $this->assertSame(0, $result['total']['orphans_count']);
    }

    public function testGetStatisticsWithMixedData(): void
    {
        $this->insertMediaObject(90010, 'With Price');
        $this->insertCheapestPrice(90010);
        $this->insertMediaObject(90011, 'Orphan 1');
        $this->insertMediaObject(90012, 'Orphan 2');

        $orphans = new TouristicOrphans();
        $result = $orphans->getStatistics([self::OBJ_TYPE_ID]);

        $stats = $result['by_object_type'][self::OBJ_TYPE_ID];
        $this->assertSame(3, $stats['visible_count']);
        $this->assertSame(1, $stats['with_prices_count']);
        $this->assertSame(2, $stats['orphans_count']);
        $this->assertSame('TestType', $stats['name']);

        $this->assertSame(3, $result['total']['visible_count']);
        $this->assertSame(2, $result['total']['orphans_count']);
        $this->assertGreaterThan(0, $result['total']['percentage_orphans']);
    }

    public function testGetStatisticsPercentageCalculation(): void
    {
        $this->insertMediaObject(90020, 'Only Orphan');

        $orphans = new TouristicOrphans();
        $result = $orphans->getStatistics([self::OBJ_TYPE_ID]);

        $this->assertEquals(100.0, $result['total']['percentage_orphans']);
    }

    // --- getOrphanDetails ---

    public function testGetOrphanDetailsNotFound(): void
    {
        $orphans = new TouristicOrphans();
        $result = $orphans->getOrphanDetails(99999);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not found', $result['error']);
    }

    public function testGetOrphanDetailsNoBookingPackages(): void
    {
        $this->insertMediaObject(90030, 'Empty Orphan');

        $orphans = new TouristicOrphans();
        $result = $orphans->getOrphanDetails(90030);

        $this->assertArrayHasKey('media_object', $result);
        $this->assertArrayHasKey('diagnosis', $result);
        $this->assertEquals(90030, $result['media_object']->id);
        $this->assertEmpty($result['booking_packages']);
        $this->assertContains('No booking packages available', $result['diagnosis']['issues']);
        $this->assertSame('issues_found', $result['diagnosis']['status']);
    }

    public function testGetOrphanDetailsWithFullTouristicData(): void
    {
        $this->insertMediaObject(90031, 'Full Orphan');
        $this->insertBookingPackage(90031, 90031);
        $this->insertDate(90031, '2027-08-01', '2027-08-10');
        $this->insertOption(90031, 90031, 'Deluxe', 599.00);

        $orphans = new TouristicOrphans();
        $result = $orphans->getOrphanDetails(90031);

        $this->assertNotEmpty($result['booking_packages']);
        $this->assertNotEmpty($result['dates']);
        $this->assertSame(0, (int) $result['cheapest_price_count']);
        $this->assertSame(1, $result['diagnosis']['summary']['booking_packages']);
        $this->assertSame(1, $result['diagnosis']['summary']['dates_total']);
    }

    public function testGetOrphanDetailsDiagnosesPastDates(): void
    {
        $this->insertMediaObject(90032, 'Past Orphan');
        $this->insertBookingPackage(90032, 90032);
        $this->insertDate(90032, '2020-01-01', '2020-01-08');
        $this->insertOption(90032, 90032, 'Standard', 199.00);

        $orphans = new TouristicOrphans();
        $result = $orphans->getOrphanDetails(90032);

        $this->assertContains('All travel dates are in the past', $result['diagnosis']['issues']);
        $this->assertSame(0, $result['diagnosis']['summary']['dates_future']);
    }

    // --- getPrimaryMediaTypeIds ---

    public function testGetPrimaryMediaTypeIds(): void
    {
        $orphans = new TouristicOrphans();
        $ids = $orphans->getPrimaryMediaTypeIds();

        $this->assertSame([self::OBJ_TYPE_ID], $ids);
    }

    public function testGetPrimaryMediaTypeIdsEmpty(): void
    {
        $config = $this->getIntegrationConfig();
        Registry::getInstance()->add('config', $config);

        $orphans = new TouristicOrphans();
        $ids = $orphans->getPrimaryMediaTypeIds();

        $this->assertSame([], $ids);
    }
}
