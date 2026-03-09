<?php

namespace Pressmind\Tests\Integration\REST\Controller;

use Pressmind\DB\Scaffolder\Mysql as ScaffolderMysql;
use Pressmind\ORM\Object\CheapestPriceSpeed;
use Pressmind\ORM\Object\Geodata;
use Pressmind\ORM\Object\Touristic\Booking\Package as BookingPackage;
use Pressmind\ORM\Object\Touristic\Date as TouristicDate;
use Pressmind\ORM\Object\Touristic\Startingpoint;
use Pressmind\ORM\Object\Touristic\Startingpoint\Option as StartingpointOption;
use Pressmind\ORM\Object\Touristic\Startingpoint\Option\ZipRange;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;
use Pressmind\Tests\Integration\FixtureLoader;
use Pressmind\REST\Controller\Ibe;
use Pressmind\Registry;

/**
 * Integration tests for the Ibe REST controller.
 * Requires a real MySQL connection via DB_HOST / DB_NAME / DB_USER / DB_PASS.
 * Fixture data is inserted in setUp and cleaned up in tearDown.
 */
class IbeIntegrationTest extends AbstractIntegrationTestCase
{
    private static bool $tablesVerified = false;
    private static bool $tablesExist = false;

    private array $fixtureData = [];
    private array $insertedIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->db === null) {
            $this->markTestSkipped('No database connection available (set DB_HOST, DB_NAME, DB_USER, DB_PASS)');
        }

        $config = Registry::getInstance()->get('config');
        $config['logging']['mode'] = 'NONE';
        $config['logging']['storage'] = [];
        Registry::getInstance()->add('config', $config);

        if (!self::$tablesVerified) {
            $this->ensureTables();
            self::$tablesExist = true;
            self::$tablesVerified = true;
        }

        $this->fixtureData = FixtureLoader::loadJsonFixture('ibe_integration_fixtures.json', 'touristic');
        $this->insertFixtures();
    }

    protected function tearDown(): void
    {
        if ($this->db !== null && self::$tablesExist) {
            $this->cleanupFixtures();
        }
        parent::tearDown();
    }

    private function ensureTables(): void
    {
        $models = [
            new CheapestPriceSpeed(),
            new Geodata(),
            new Startingpoint(),
            new StartingpointOption(),
            new BookingPackage(),
            new TouristicDate(),
            new ZipRange(),
        ];
        foreach ($models as $model) {
            try {
                $scaffolder = new ScaffolderMysql($model);
                $scaffolder->run(false);
            } catch (\Throwable $e) {
            }
        }
    }

    private function hasTable(string $table): bool
    {
        try {
            $this->db->fetchAll("SELECT 1 FROM {$table} LIMIT 1");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function insertFixtures(): void
    {
        $cps = $this->fixtureData['cheapest_price_speed'];
        foreach ($cps as $key => $value) {
            if (str_ends_with($key, '_offset') && is_numeric($value)) {
                $realKey = str_replace('_offset', '', $key);
                $cps[$realKey] = FixtureLoader::resolveDate((int) $value)->format('Y-m-d H:i:s');
                unset($cps[$key]);
            }
        }
        $this->db->insert('pmt2core_cheapest_price_speed', $cps, true);
        $this->insertedIds['cheapest_price_speed'] = $cps['id'];

        $sp = $this->fixtureData['startingpoint'];
        $this->db->insert('pmt2core_touristic_startingpoints', $sp, true);
        $this->insertedIds['startingpoint'] = $sp['id'];

        foreach ($this->fixtureData['startingpoint_options'] as $opt) {
            $this->db->insert('pmt2core_touristic_startingpoint_options', $opt, true);
            $this->insertedIds['startingpoint_options'][] = $opt['id'];
        }

        foreach ($this->fixtureData['geodata'] as $geo) {
            $this->db->insert('pmt2core_geodata', $geo, true);
            $this->insertedIds['geodata'][] = $geo['postleitzahl'];
        }

        if (!empty($this->fixtureData['zip_ranges'])) {
            foreach ($this->fixtureData['zip_ranges'] as $zr) {
                $this->db->insert('pmt2core_touristic_startingpoint_options_zip_ranges', $zr, true);
                $this->insertedIds['zip_ranges'][] = $zr['id'];
            }
        }

        if (
            isset($this->fixtureData['touristic_booking_package'])
            && $this->hasTable('pmt2core_touristic_booking_packages')
        ) {
            $bp = $this->fixtureData['touristic_booking_package'];
            $this->db->insert('pmt2core_touristic_booking_packages', $bp, true);
            $this->insertedIds['touristic_booking_package'] = $bp['id'];
        }

        if (
            isset($this->fixtureData['touristic_date'])
            && $this->hasTable('pmt2core_touristic_dates')
        ) {
            $td = $this->fixtureData['touristic_date'];
            if (isset($td['departure_offset'], $td['arrival_offset'])) {
                $td['departure'] = FixtureLoader::resolveDate((int) $td['departure_offset'])->format('Y-m-d');
                $td['arrival'] = FixtureLoader::resolveDate((int) $td['arrival_offset'])->format('Y-m-d');
                unset($td['departure_offset'], $td['arrival_offset']);
            }
            $this->db->insert('pmt2core_touristic_dates', $td, true);
            $this->insertedIds['touristic_date'] = $td['id'];
        }
    }

    private function cleanupFixtures(): void
    {
        try {
            if (isset($this->insertedIds['touristic_date']) && $this->hasTable('pmt2core_touristic_dates')) {
                $this->db->delete('pmt2core_touristic_dates', ['id = ?', $this->insertedIds['touristic_date']]);
            }
            if (isset($this->insertedIds['touristic_booking_package']) && $this->hasTable('pmt2core_touristic_booking_packages')) {
                $this->db->delete('pmt2core_touristic_booking_packages', ['id = ?', $this->insertedIds['touristic_booking_package']]);
            }
            if (isset($this->insertedIds['cheapest_price_speed'])) {
                $this->db->delete('pmt2core_cheapest_price_speed', ['id = ?', $this->insertedIds['cheapest_price_speed']]);
            }
            if (isset($this->insertedIds['startingpoint'])) {
                $this->db->delete('pmt2core_touristic_startingpoints', ['id = ?', $this->insertedIds['startingpoint']]);
            }
            if (!empty($this->insertedIds['startingpoint_options'])) {
                foreach ($this->insertedIds['startingpoint_options'] as $id) {
                    $this->db->delete('pmt2core_touristic_startingpoint_options', ['id = ?', $id]);
                }
            }
            if (!empty($this->insertedIds['geodata'])) {
                foreach ($this->insertedIds['geodata'] as $zip) {
                    $this->db->delete('pmt2core_geodata', ['postleitzahl = ?', $zip]);
                }
            }
            if (!empty($this->insertedIds['zip_ranges'])) {
                foreach ($this->insertedIds['zip_ranges'] as $id) {
                    $this->db->delete('pmt2core_touristic_startingpoint_options_zip_ranges', ['id = ?', $id]);
                }
            }
        } catch (\Throwable $e) {
            // best-effort cleanup
        }
    }

    // ---------------------------------------------------------------
    // getCheapestPrice
    // ---------------------------------------------------------------

    public function testGetCheapestPriceWithValidFixture(): void
    {
        $controller = new Ibe();
        $result = $controller->getCheapestPrice([
            'data' => ['id_cheapest_price' => $this->insertedIds['cheapest_price_speed']],
        ]);
        $this->assertTrue($result['success']);
        $this->assertNotNull($result['data']);
        $this->assertEquals(999001, $result['data']->id);
        $this->assertEquals(399.00, (float) $result['data']->price_total);
        $this->assertEquals('date_housing', $result['data']->price_mix);
    }

    public function testGetCheapestPriceDataIsJsonSerializable(): void
    {
        $controller = new Ibe();
        $result = $controller->getCheapestPrice([
            'data' => ['id_cheapest_price' => $this->insertedIds['cheapest_price_speed']],
        ]);
        $json = json_encode($result);
        $this->assertNotFalse($json);
        $decoded = json_decode($json, true);
        $this->assertTrue($decoded['success']);
        $this->assertNotNull($decoded['data']);
    }

    public function testGetCheapestPriceWithNonExistentIdReturnsNull(): void
    {
        $controller = new Ibe();
        $result = $controller->getCheapestPrice([
            'data' => ['id_cheapest_price' => 0],
        ]);
        $this->assertFalse($result['success']);
    }

    // ---------------------------------------------------------------
    // getRequestableOffer
    // ---------------------------------------------------------------

    public function testGetRequestableOfferWithValidFixture(): void
    {
        if (!isset($this->insertedIds['touristic_date'])) {
            $this->markTestSkipped('Touristic date fixture not loaded (table pmt2core_touristic_dates may not exist)');
        }
        $controller = new Ibe();
        $result = $controller->getRequestableOffer([
            'data' => ['id_cheapest_price' => $this->insertedIds['cheapest_price_speed']],
        ]);
        $this->assertTrue($result['success']);
        $this->assertNotNull($result['CheapestPriceSpeed']);
        $this->assertIsArray($result['Options']);
        $this->assertArrayHasKey('alternativeOptions', $result);
        $this->assertIsArray($result['alternativeOptions']);
    }

    public function testGetRequestableOfferResponseStructure(): void
    {
        $controller = new Ibe();
        $result = $controller->getRequestableOffer([
            'data' => ['id_cheapest_price' => $this->insertedIds['cheapest_price_speed']],
        ]);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('CheapestPriceSpeed', $result);
        $this->assertArrayHasKey('Options', $result);
        if ($result['success'] && $result['CheapestPriceSpeed'] !== null) {
            $this->assertArrayHasKey('alternativeOptions', $result);
        }
    }

    // ---------------------------------------------------------------
    // pressmind_ib3_v2_get_geodata_status
    // ---------------------------------------------------------------

    public function testGeodataStatusReturnsPositiveCount(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_geodata_status([]);
        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['data']['geodata_count']);
        $this->assertTrue($result['data']['has_geodata']);
    }

    public function testGeodataStatusResponseIsJsonSerializable(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_geodata_status([]);
        $json = json_encode($result);
        $this->assertNotFalse($json);
        $decoded = json_decode($json, true);
        $this->assertIsInt($decoded['data']['geodata_count']);
        $this->assertIsBool($decoded['data']['has_geodata']);
    }

    // ---------------------------------------------------------------
    // pressmind_ib3_v2_get_starting_point_options
    // ---------------------------------------------------------------

    public function testGetStartingPointOptionsReturnsFixtureData(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_starting_point_options([
            'data' => [
                'id_starting_point' => 'sp_test_999',
                'limit' => 10,
            ],
        ]);
        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(2, $result['data']['total']);
        $this->assertNotEmpty($result['data']['starting_point_options']);
    }

    public function testGetStartingPointOptionsRespectsLimit(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_starting_point_options([
            'data' => [
                'id_starting_point' => 'sp_test_999',
                'limit' => 1,
            ],
        ]);
        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['data']['starting_point_options']);
        $this->assertGreaterThanOrEqual(2, $result['data']['total']);
    }

    public function testGetStartingPointOptionsResponseIsJsonSerializable(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_starting_point_options([
            'data' => [
                'id_starting_point' => 'sp_test_999',
                'limit' => 10,
            ],
        ]);
        $json = json_encode($result);
        $this->assertNotFalse($json);
    }

    public function testGetStartingPointOptionsPaginationWithStartOffset(): void
    {
        $controller = new Ibe();
        $full = $controller->pressmind_ib3_v2_get_starting_point_options([
            'data' => [
                'id_starting_point' => 'sp_test_999',
                'limit' => 10,
                'start' => 0,
            ],
        ]);
        $paged = $controller->pressmind_ib3_v2_get_starting_point_options([
            'data' => [
                'id_starting_point' => 'sp_test_999',
                'limit' => 1,
                'start' => 1,
            ],
        ]);
        $this->assertTrue($full['success']);
        $this->assertTrue($paged['success']);
        $this->assertCount(1, $paged['data']['starting_point_options']);
        $this->assertGreaterThanOrEqual(2, $paged['data']['total']);
        if (count($full['data']['starting_point_options']) >= 2) {
            $this->assertNotEquals(
                $full['data']['starting_point_options'][0]->id ?? $full['data']['starting_point_options'][0]['id'] ?? null,
                $paged['data']['starting_point_options'][0]->id ?? $paged['data']['starting_point_options'][0]['id'] ?? null
            );
        }
    }

    // ---------------------------------------------------------------
    // pressmind_ib3_v2_get_starting_point_option_by_id
    // ---------------------------------------------------------------

    public function testGetStartingPointOptionByIdReturnsFixtureOption(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_starting_point_option_by_id([
            'data' => [
                'id_starting_point_option' => 'spo_test_001',
            ],
        ]);
        $this->assertTrue($result['success']);
        $option = $result['data']['starting_point_option'];
        $this->assertNotFalse($option);
        $this->assertEquals('BER', $option->code);
        $this->assertEquals('Berlin Alexanderplatz', $option->name);
    }

    public function testGetStartingPointOptionByIdNotFoundReturns(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_starting_point_option_by_id([
            'data' => [
                'id_starting_point_option' => 'nonexistent_99999',
            ],
        ]);
        $this->assertTrue($result['success']);
        $this->assertFalse($result['data']['starting_point_option']);
    }

    // ---------------------------------------------------------------
    // pressmind_ib3_v2_find_pickup_service
    // ---------------------------------------------------------------

    public function testFindPickupServiceReturnsFixtureData(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_find_pickup_service([
            'data' => [
                'id_starting_point' => 'sp_test_999',
            ],
        ]);
        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(1, $result['data']['total']);
    }

    public function testFindPickupServiceWithZipFilter(): void
    {
        $controller = new Ibe();
        $resultWithZip = $controller->pressmind_ib3_v2_find_pickup_service([
            'data' => [
                'id_starting_point' => 'sp_test_999',
                'zip' => '10115',
            ],
        ]);
        $this->assertTrue($resultWithZip['success']);
        $this->assertArrayHasKey('total', $resultWithZip['data']);
        $this->assertArrayHasKey('starting_point_options', $resultWithZip['data']);
        $this->assertGreaterThanOrEqual(1, $resultWithZip['data']['total'], 'Fixture has pickup option with zip 10115');
    }

    // ---------------------------------------------------------------
    // pressmind_ib3_v2_test (sanity check)
    // ---------------------------------------------------------------

    public function testIb3TestEndpointWithRealDb(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_test(['integration' => true]);
        $this->assertTrue($result['success']);
        $this->assertSame('Test erfolgreich', $result['msg']);
    }
}
