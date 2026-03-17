<?php

namespace Pressmind\Tests\Unit\REST\Controller;

use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\Ibe;
use Pressmind\Registry;

class IbeTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $config = Registry::getInstance()->get('config');
        $config['logging']['mode'] = 'NONE';
        $config['logging']['storage'] = [];
        Registry::getInstance()->add('config', $config);
    }

    protected function createCustomMockDb(array $overrides = []): AdapterInterface
    {
        $adapter = $this->createMock(AdapterInterface::class);

        if (isset($overrides['fetchAllCallback'])) {
            $adapter->method('fetchAll')->willReturnCallback($overrides['fetchAllCallback']);
        } else {
            $adapter->method('fetchAll')->willReturn($overrides['fetchAll'] ?? []);
        }

        if (isset($overrides['fetchRowCallback'])) {
            $adapter->method('fetchRow')->willReturnCallback($overrides['fetchRowCallback']);
        } else {
            $adapter->method('fetchRow')->willReturn($overrides['fetchRow'] ?? null);
        }

        $adapter->method('fetchOne')->willReturn($overrides['fetchOne'] ?? null);
        $adapter->method('getAffectedRows')->willReturn(0);
        $adapter->method('getTablePrefix')->willReturn('pmt2core_');
        $adapter->method('inTransaction')->willReturn(false);
        $adapter->method('execute')->willReturn(null);
        $adapter->method('insert')->willReturn(null);
        $adapter->method('replace')->willReturn(null);
        $adapter->method('update')->willReturn(null);
        $adapter->method('delete')->willReturn(null);
        $adapter->method('truncate')->willReturn(null);
        $adapter->method('batchInsert')->willReturn(1);
        $adapter->method('beginTransaction')->willReturn(null);
        $adapter->method('commit')->willReturn(null);
        $adapter->method('rollback')->willReturn(null);
        return $adapter;
    }

    // ---------------------------------------------------------------
    // pressmind_ib3_v2_test
    // ---------------------------------------------------------------

    public function testPressmindIb3V2TestReturnsSuccess(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_test(['foo' => 'bar']);
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame('Test erfolgreich', $result['msg']);
        $this->assertArrayHasKey('debug', $result);
        $this->assertSame(['foo' => 'bar'], $result['debug']);
    }

    public function testPressmindIb3V2TestWithEmptyParams(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_test([]);
        $this->assertTrue($result['success']);
        $this->assertSame([], $result['debug']);
    }

    public function testPressmindIb3V2TestWithNestedParams(): void
    {
        $controller = new Ibe();
        $params = ['data' => ['params' => ['imo' => 1]], 'settings' => ['lang' => 'de']];
        $result = $controller->pressmind_ib3_v2_test($params);
        $this->assertTrue($result['success']);
        $this->assertSame($params, $result['debug']);
    }

    // ---------------------------------------------------------------
    // pressmind_ib3_v2_get_touristic_object
    // ---------------------------------------------------------------

    public function testPressmindIb3V2GetTouristicObjectWithMissingParamsReturnsError(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_touristic_object([
            'data' => ['params' => []],
        ]);
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('parameters are missing', $result['msg']);
        $this->assertNull($result['data']);
    }

    public function testPressmindIb3V2GetTouristicObjectWithEmptyDataReturnsError(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_touristic_object(['data' => []]);
        $this->assertFalse($result['success']);
    }

    public function testPressmindIb3V2GetTouristicObjectMissingImoReturnsError(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_touristic_object([
            'data' => ['params' => ['idbp' => 2, 'idd' => 3]],
        ]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('parameters are missing', $result['msg']);
    }

    public function testPressmindIb3V2GetTouristicObjectMissingIdbpReturnsError(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_touristic_object([
            'data' => ['params' => ['imo' => 1, 'idd' => 3]],
        ]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('parameters are missing', $result['msg']);
    }

    public function testPressmindIb3V2GetTouristicObjectMissingIddReturnsError(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_touristic_object([
            'data' => ['params' => ['imo' => 1, 'idbp' => 2]],
        ]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('parameters are missing', $result['msg']);
    }

    public function testPressmindIb3V2GetTouristicObjectDateNotFoundReturnsError(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_touristic_object([
            'data' => [
                'params' => ['imo' => 1, 'idbp' => 2, 'idd' => 3],
            ],
        ]);
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertSame('not_found', $result['code']);
        $this->assertNull($result['data']);
        $this->assertStringContainsString('date not found', $result['msg']);
    }

    public function testPressmindIb3V2GetTouristicObjectDateNotFoundContainsDateId(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_touristic_object([
            'data' => [
                'params' => ['imo' => 1, 'idbp' => 2, 'idd' => 'date_42'],
            ],
        ]);
        $this->assertStringContainsString('date_42', $result['msg']);
    }

    public function testPressmindIb3V2GetTouristicObjectWithSettingsDoesNotCrash(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_touristic_object([
            'data' => [
                'params' => ['imo' => 1, 'idbp' => 'bp_1', 'idd' => 'date_1'],
                'settings' => [
                    'steps' => [
                        'starting_points' => [
                            'pagination_page_size' => ['value' => 20],
                            'default_starting_point_codes' => ['value' => ['BER', 'HAM']],
                        ],
                    ],
                ],
            ],
        ]);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);
        $this->assertSame('not_found', $result['code']);
    }

    public function testPressmindIb3V2GetTouristicObjectWithAgencyParamDoesNotCrash(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_touristic_object([
            'data' => [
                'params' => ['imo' => 1, 'idbp' => 'bp_1', 'idd' => 'date_1', 'ida' => 'agency_1'],
            ],
        ]);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);
    }

    public function testPressmindIb3V2GetTouristicObjectWithIbeClientParamDoesNotCrash(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_touristic_object([
            'data' => [
                'params' => ['imo' => 1, 'idbp' => 'bp_1', 'idd' => 'date_1', 'iic' => 'client_xyz'],
            ],
        ]);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);
    }

    public function testPressmindIb3V2GetTouristicObjectErrorResponseStructure(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_touristic_object([
            'data' => ['params' => []],
        ]);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('msg', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertFalse($result['success']);
        $this->assertNull($result['data']);
    }

    public function testPressmindIb3V2GetTouristicObjectDateNotFoundResponseStructure(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_touristic_object([
            'data' => ['params' => ['imo' => 1, 'idbp' => 'bp_1', 'idd' => 'date_999']],
        ]);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('msg', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('code', $result);
        $this->assertSame('not_found', $result['code']);
        $this->assertNull($result['data']);
    }

    /**
     * Expected top-level keys in success response data (for documentation and integration test validation).
     * Full happy-path unit test would require extensive DB mocking of Booking, MediaObject, Date, Package, etc.
     */
    public function testPressmindIb3V2GetTouristicObjectSuccessDataKeysDocumentation(): void
    {
        $expectedDataKeys = [
            'date', 'transport_pairs', 'starting_points', 'exit_points', 'pickup_services',
            'has_pickup_services', 'has_starting_points', 'has_seatplan', 'product',
            'housing_packages', 'option_discounts', 'earlybird', 'extras', 'insurances',
            'insurance_price_table_packages', 'id_ibe', 'code_ibe', 'product_type_ibe',
            'booking_transaction_recipients', 'booking_package_created_date',
        ];
        $this->assertNotEmpty($expectedDataKeys);
        $this->assertContains('date', $expectedDataKeys);
        $this->assertContains('product', $expectedDataKeys);
        $this->assertContains('housing_packages', $expectedDataKeys);
    }

    // ---------------------------------------------------------------
    // getCheapestPrice
    // ---------------------------------------------------------------

    public function testGetCheapestPriceWithMissingParamReturnsError(): void
    {
        $controller = new Ibe();
        $result = $controller->getCheapestPrice(['data' => []]);
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('parameters are missing', $result['msg']);
        $this->assertStringContainsString('id_cheapest_price', $result['msg']);
        $this->assertNull($result['data']);
    }

    public function testGetCheapestPriceWithEmptyStringIdReturnsError(): void
    {
        $controller = new Ibe();
        $result = $controller->getCheapestPrice(['data' => ['id_cheapest_price' => '']]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('parameters are missing', $result['msg']);
    }

    public function testGetCheapestPriceWithZeroIdReturnsError(): void
    {
        $controller = new Ibe();
        $result = $controller->getCheapestPrice(['data' => ['id_cheapest_price' => 0]]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('parameters are missing', $result['msg']);
    }

    public function testGetCheapestPriceWithInvalidIdReturnsNullData(): void
    {
        $controller = new Ibe();
        $result = $controller->getCheapestPrice(['data' => ['id_cheapest_price' => 99999]]);
        $this->assertTrue($result['success']);
        $this->assertNull($result['data']);
    }

    public function testGetCheapestPriceWithValidIdReturnsData(): void
    {
        $mockRow = new \stdClass();
        $mockRow->id = 1;
        $mockRow->id_media_object = 100;
        $mockRow->price_total = 299.00;

        $db = $this->createCustomMockDb(['fetchRow' => $mockRow]);
        Registry::getInstance()->add('db', $db);

        $controller = new Ibe();
        $result = $controller->getCheapestPrice(['data' => ['id_cheapest_price' => 1]]);
        $this->assertTrue($result['success']);
        $this->assertNotNull($result['data']);
        $this->assertIsObject($result['data']);
        $this->assertEquals(1, $result['data']->id);
        $this->assertEquals(299.00, $result['data']->price_total);
    }

    public function testGetCheapestPriceErrorResponseHasParamsKey(): void
    {
        $controller = new Ibe();
        $result = $controller->getCheapestPrice(['data' => []]);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('msg', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('params', $result);
        $this->assertSame([], $result['params']);
    }

    public function testGetCheapestPriceErrorResponseWithZeroIdHasParamsKey(): void
    {
        $controller = new Ibe();
        $result = $controller->getCheapestPrice(['data' => ['id_cheapest_price' => 0]]);
        $this->assertArrayHasKey('params', $result);
        $this->assertSame(['id_cheapest_price' => 0], $result['params']);
    }

    public function testGetCheapestPriceValidResponseHasExpectedProperties(): void
    {
        $mockRow = new \stdClass();
        $mockRow->id = 42;
        $mockRow->id_media_object = 100;
        $mockRow->id_booking_package = 'bp_1';
        $mockRow->id_date = 'date_1';
        $mockRow->id_option = 'opt_1';
        $mockRow->duration = 7;
        $mockRow->option_name = 'Doppelzimmer';
        $mockRow->price_mix = 'date_housing';
        $mockRow->price_total = 399.00;
        $mockRow->price_option = 199.00;
        $mockRow->state = 0;

        $db = $this->createCustomMockDb(['fetchRow' => $mockRow]);
        Registry::getInstance()->add('db', $db);

        $controller = new Ibe();
        $result = $controller->getCheapestPrice(['data' => ['id_cheapest_price' => 42]]);
        $this->assertTrue($result['success']);
        $data = $result['data'];
        $this->assertObjectHasProperty('id', $data);
        $this->assertObjectHasProperty('id_media_object', $data);
        $this->assertObjectHasProperty('price_mix', $data);
        $this->assertObjectHasProperty('duration', $data);
        $this->assertObjectHasProperty('option_name', $data);
        $this->assertObjectHasProperty('price_total', $data);
        $this->assertEquals(42, $data->id);
        $this->assertEquals(100, $data->id_media_object);
        $this->assertSame('date_housing', $data->price_mix);
        $this->assertEquals(7, $data->duration);
        $this->assertSame('Doppelzimmer', $data->option_name);
        $this->assertIsNumeric($data->price_total);
        $this->assertEquals(399.00, (float) $data->price_total);
    }

    // ---------------------------------------------------------------
    // getRequestableOffer
    // ---------------------------------------------------------------

    public function testGetRequestableOfferWithMissingParamReturnsError(): void
    {
        $controller = new Ibe();
        $result = $controller->getRequestableOffer(['data' => []]);
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('parameters are missing', $result['msg']);
        $this->assertNull($result['data']);
    }

    public function testGetRequestableOfferWithZeroIdReturnsError(): void
    {
        $controller = new Ibe();
        $result = $controller->getRequestableOffer(['data' => ['id_cheapest_price' => 0]]);
        $this->assertFalse($result['success']);
    }

    public function testGetRequestableOfferWithInvalidIdReturnsNull(): void
    {
        $controller = new Ibe();
        $result = $controller->getRequestableOffer(['data' => ['id_cheapest_price' => 99999]]);
        $this->assertTrue($result['success']);
        $this->assertNull($result['CheapestPriceSpeed']);
        $this->assertNull($result['Options']);
    }

    public function testGetRequestableOfferWithValidIdReturnsStructure(): void
    {
        $cheapestRow = new \stdClass();
        $cheapestRow->id = 1;
        $cheapestRow->id_media_object = 100;
        $cheapestRow->id_date = 'date_50';
        $cheapestRow->id_option = 'opt_10';
        $cheapestRow->price_mix = 'date_housing';
        $cheapestRow->price_total = 399.00;

        $dateRow = new \stdClass();
        $dateRow->id = 'date_50';
        $dateRow->id_media_object = 100;
        $dateRow->id_booking_package = 'bp_1';
        $dateRow->id_starting_point = null;
        $dateRow->departure = '2026-06-01';
        $dateRow->arrival = '2026-06-08';
        $dateRow->season = 'S';
        $dateRow->state = 0;
        $dateRow->code = null;

        $db = $this->createCustomMockDb([
            'fetchRowCallback' => function ($query) use ($cheapestRow, $dateRow) {
                if (strpos($query, 'pmt2core_cheapest_price_speed') !== false) {
                    return $cheapestRow;
                }
                if (strpos($query, 'pmt2core_touristic_dates') !== false) {
                    return $dateRow;
                }
                return null;
            },
        ]);
        Registry::getInstance()->add('db', $db);

        $controller = new Ibe();
        $result = $controller->getRequestableOffer(['data' => ['id_cheapest_price' => 1]]);
        $this->assertTrue($result['success']);
        $this->assertNotNull($result['CheapestPriceSpeed']);
        $this->assertIsObject($result['CheapestPriceSpeed']);
        $this->assertEquals(1, $result['CheapestPriceSpeed']->id);
        $this->assertIsArray($result['Options']);
        $this->assertIsArray($result['alternativeOptions']);
    }

    public function testGetRequestableOfferErrorResponseHasParamsKey(): void
    {
        $controller = new Ibe();
        $result = $controller->getRequestableOffer(['data' => []]);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('msg', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('params', $result);
        $this->assertSame([], $result['params']);
        $this->assertStringContainsString('id_cheapest_price', $result['msg']);
    }

    public function testGetRequestableOfferInvalidIdResponseHasNoAlternativeOptionsKey(): void
    {
        $controller = new Ibe();
        $result = $controller->getRequestableOffer(['data' => ['id_cheapest_price' => 99999]]);
        $this->assertTrue($result['success']);
        $this->assertNull($result['CheapestPriceSpeed']);
        $this->assertNull($result['Options']);
        $this->assertArrayNotHasKey('alternativeOptions', $result);
    }

    public function testGetRequestableOfferAlternativeOptionsExcludesCurrentOption(): void
    {
        $cheapestRow = new \stdClass();
        $cheapestRow->id = 1;
        $cheapestRow->id_media_object = 100;
        $cheapestRow->id_date = 'date_50';
        $cheapestRow->id_option = 'opt_current';
        $cheapestRow->price_mix = 'date_housing';
        $cheapestRow->price_total = 399.00;

        $dateRow = new \stdClass();
        $dateRow->id = 'date_50';
        $dateRow->id_media_object = 100;
        $dateRow->id_booking_package = 'bp_1';
        $dateRow->id_starting_point = null;
        $dateRow->departure = '2026-06-01';
        $dateRow->arrival = '2026-06-08';
        $dateRow->season = 'S';
        $dateRow->state = 0;
        $dateRow->code = null;

        $optCurrent = new \stdClass();
        $optCurrent->id = 'opt_current';
        $optCurrent->id_media_object = 100;
        $optCurrent->id_booking_package = 'bp_1';
        $optCurrent->type = 'housing_option';
        $optCurrent->price = 199.00;
        $optCurrent->price_pseudo = 0;
        $optCurrent->price_child = 0;
        $optCurrent->occupancy = 2;
        $optCurrent->occupancy_child = 0;
        $optCurrent->renewal_duration = 0;
        $optCurrent->renewal_price = 0;
        $optCurrent->order = 0;
        $optCurrent->booking_type = 0;
        $optCurrent->state = 0;
        $optCurrent->min_pax = 0;
        $optCurrent->max_pax = 99;

        $optOther = new \stdClass();
        $optOther->id = 'opt_other';
        $optOther->id_media_object = 100;
        $optOther->id_booking_package = 'bp_1';
        $optOther->type = 'housing_option';
        $optOther->price = 299.00;
        $optOther->price_pseudo = 0;
        $optOther->price_child = 0;
        $optOther->occupancy = 2;
        $optOther->occupancy_child = 0;
        $optOther->renewal_duration = 0;
        $optOther->renewal_price = 0;
        $optOther->order = 0;
        $optOther->booking_type = 0;
        $optOther->state = 0;
        $optOther->min_pax = 0;
        $optOther->max_pax = 99;

        $optionsForDate = [$optCurrent, $optOther];

        $db = $this->createCustomMockDb([
            'fetchRowCallback' => function ($query) use ($cheapestRow, $dateRow) {
                if (strpos($query, 'pmt2core_cheapest_price_speed') !== false) {
                    return $cheapestRow;
                }
                if (strpos($query, 'pmt2core_touristic_dates') !== false) {
                    return $dateRow;
                }
                return null;
            },
            'fetchAllCallback' => function ($query) use ($optionsForDate) {
                if (strpos($query, 'pmt2core_touristic_options') !== false) {
                    return $optionsForDate;
                }
                return [];
            },
        ]);
        Registry::getInstance()->add('db', $db);

        $controller = new Ibe();
        $result = $controller->getRequestableOffer(['data' => ['id_cheapest_price' => 1]]);
        $this->assertTrue($result['success']);
        $this->assertIsArray($result['alternativeOptions']);
        foreach ($result['alternativeOptions'] as $option) {
            $id = is_object($option) ? $option->id : $option['id'] ?? null;
            $this->assertNotSame('opt_current', $id, 'Current option must be excluded from alternativeOptions');
        }
        $ids = array_map(function ($o) {
            return is_object($o) ? $o->id : ($o['id'] ?? null);
        }, $result['alternativeOptions']);
        $this->assertContains('opt_other', $ids);
        $this->assertNotContains('opt_current', $ids);
    }

    // ---------------------------------------------------------------
    // pressmind_ib3_v2_get_geodata_status
    // ---------------------------------------------------------------

    public function testPressmindIb3V2GetGeodataStatusWithNoData(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_geodata_status([]);
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['data']['geodata_count']);
        $this->assertFalse($result['data']['has_geodata']);
    }

    public function testPressmindIb3V2GetGeodataStatusWithData(): void
    {
        $db = $this->createCustomMockDb([
            'fetchAll' => [(object)['cnt' => 42]]
        ]);
        Registry::getInstance()->add('db', $db);

        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_geodata_status([]);
        $this->assertTrue($result['success']);
        $this->assertSame(42, $result['data']['geodata_count']);
        $this->assertTrue($result['data']['has_geodata']);
    }

    public function testPressmindIb3V2GetGeodataStatusResponseKeys(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_geodata_status([]);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('geodata_count', $result['data']);
        $this->assertArrayHasKey('has_geodata', $result['data']);
    }

    // ---------------------------------------------------------------
    // pressmind_ib3_v2_get_starting_point_options
    // ---------------------------------------------------------------

    public function testPressmindIb3V2GetStartingPointOptionsReturnsEmptyList(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_starting_point_options([
            'data' => [
                'id_starting_point' => 'sp_1',
                'limit' => 10,
            ],
        ]);
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['data']['total']);
        $this->assertEmpty($result['data']['starting_point_options']);
    }

    public function testPressmindIb3V2GetStartingPointOptionsWithCustomLimit(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_starting_point_options([
            'data' => [
                'id_starting_point' => 'sp_1',
                'limit' => 5,
                'start' => 0,
            ],
        ]);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('total', $result['data']);
        $this->assertArrayHasKey('starting_point_options', $result['data']);
    }

    public function testPressmindIb3V2GetStartingPointOptionsWithZipRadius(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_starting_point_options([
            'data' => [
                'id_starting_point' => 'sp_1',
                'limit' => 10,
                'zip' => '10115',
                'radius' => 30,
            ],
        ]);
        $this->assertTrue($result['success']);
        $this->assertIsArray($result['data']['starting_point_options']);
    }

    public function testPressmindIb3V2GetStartingPointOptionsWithIbeClient(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_starting_point_options([
            'data' => [
                'id_starting_point' => 'sp_1',
                'limit' => 10,
                'iic' => 'client_xyz',
            ],
        ]);
        $this->assertTrue($result['success']);
    }

    public function testPressmindIb3V2GetStartingPointOptionsWithOrderByCodeList(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_starting_point_options([
            'data' => [
                'id_starting_point' => 'sp_1',
                'limit' => 10,
                'order_by_code_list' => ['BER', 'HAM'],
            ],
        ]);
        $this->assertTrue($result['success']);
    }

    public function testPressmindIb3V2GetStartingPointOptionsWithNullLimitUsesDefault(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_starting_point_options([
            'data' => [
                'id_starting_point' => 'sp_1',
                'limit' => null,
            ],
        ]);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('starting_point_options', $result['data']);
        $this->assertIsArray($result['data']['starting_point_options']);
    }

    public function testPressmindIb3V2GetStartingPointOptionsWithStartOffset(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_starting_point_options([
            'data' => [
                'id_starting_point' => 'sp_1',
                'limit' => 10,
                'start' => 5,
            ],
        ]);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('total', $result['data']);
        $this->assertArrayHasKey('starting_point_options', $result['data']);
    }

    public function testPressmindIb3V2GetStartingPointOptionsWithNullRadius(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_starting_point_options([
            'data' => [
                'id_starting_point' => 'sp_1',
                'limit' => 10,
                'radius' => null,
                'zip' => '10115',
            ],
        ]);
        $this->assertTrue($result['success']);
        $this->assertIsArray($result['data']['starting_point_options']);
    }

    public function testPressmindIb3V2GetStartingPointOptionsWithZeroLimitReturnsEmptyOptions(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_starting_point_options([
            'data' => [
                'id_starting_point' => 'sp_1',
                'limit' => 0,
            ],
        ]);
        $this->assertTrue($result['success']);
        $this->assertCount(0, $result['data']['starting_point_options']);
    }

    // ---------------------------------------------------------------
    // pressmind_ib3_v2_get_starting_point_option_by_id
    // ---------------------------------------------------------------

    public function testPressmindIb3V2GetStartingPointOptionByIdNotFound(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_starting_point_option_by_id([
            'data' => [
                'id_starting_point_option' => 'opt_999',
            ],
        ]);
        $this->assertTrue($result['success']);
        $this->assertFalse($result['data']['starting_point_option']);
    }

    public function testPressmindIb3V2GetStartingPointOptionByIdWithIbeClient(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_get_starting_point_option_by_id([
            'data' => [
                'id_starting_point_option' => 'opt_999',
                'ibe_client' => 'client_abc',
            ],
        ]);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('starting_point_option', $result['data']);
    }

    // ---------------------------------------------------------------
    // pressmind_ib3_v2_find_pickup_service
    // ---------------------------------------------------------------

    public function testPressmindIb3V2FindPickupServiceReturnsEmptyList(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_find_pickup_service([
            'data' => [
                'id_starting_point' => 'sp_1',
            ],
        ]);
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['data']['total']);
        $this->assertEmpty($result['data']['starting_point_options']);
    }

    public function testPressmindIb3V2FindPickupServiceWithZip(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_find_pickup_service([
            'data' => [
                'id_starting_point' => 'sp_1',
                'zip' => '10115',
            ],
        ]);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('total', $result['data']);
        $this->assertArrayHasKey('starting_point_options', $result['data']);
    }

    public function testPressmindIb3V2FindPickupServiceWithIbeClient(): void
    {
        $controller = new Ibe();
        $result = $controller->pressmind_ib3_v2_find_pickup_service([
            'data' => [
                'id_starting_point' => 'sp_1',
                'iic' => 'client_xyz',
            ],
        ]);
        $this->assertTrue($result['success']);
    }

    // ---------------------------------------------------------------
    // syncAvailabilityState
    // ---------------------------------------------------------------

    public function testSyncAvailabilityStateMissingIdMediaObjectReturnsError(): void
    {
        $controller = new Ibe();
        $result = $controller->syncAvailabilityState(['data' => ['leistungen' => []]]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('id_media_object', $result['msg']);
        $this->assertFalse($result['changed']);
        $this->assertSame(0, $result['changes_applied']);
        $this->assertEmpty($result['applied_changes']);
    }

    public function testSyncAvailabilityStateEmptyLeistungenReturnsNoChange(): void
    {
        $controller = new Ibe();
        $result = $controller->syncAvailabilityState(['data' => ['id_media_object' => 12345, 'leistungen' => []]]);
        $this->assertTrue($result['success']);
        $this->assertFalse($result['changed']);
        $this->assertSame(0, $result['changes_applied']);
        $this->assertEmpty($result['applied_changes']);
    }

    public function testSyncAvailabilityStateMissingLeistungenReturnsNoChange(): void
    {
        $controller = new Ibe();
        $result = $controller->syncAvailabilityState(['data' => ['id_media_object' => 12345]]);
        $this->assertTrue($result['success']);
        $this->assertFalse($result['changed']);
        $this->assertSame(0, $result['changes_applied']);
    }

    public function testSyncAvailabilityStateInvalidStateSkipped(): void
    {
        $db = $this->createCustomMockDb(['fetchAll' => []]);
        Registry::getInstance()->add('db', $db);

        $controller = new Ibe();
        $result = $controller->syncAvailabilityState([
            'data' => [
                'id_media_object' => 3509653,
                'leistungen' => [
                    ['code_ibe' => '123', 'new_state' => 99, 'crs_status' => 'unknown'],
                ],
            ],
        ]);
        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['changes_applied']);
    }

    public function testSyncAvailabilityStateResponseStructure(): void
    {
        $controller = new Ibe();
        $result = $controller->syncAvailabilityState(['data' => ['id_media_object' => 1, 'leistungen' => []]]);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('changed', $result);
        $this->assertArrayHasKey('changes_applied', $result);
        $this->assertArrayHasKey('applied_changes', $result);
    }

    public function testSyncAvailabilityStateUnmatchedCodeIbeSkipped(): void
    {
        $db = $this->createCustomMockDb(['fetchAll' => []]);
        Registry::getInstance()->add('db', $db);

        $controller = new Ibe();
        $result = $controller->syncAvailabilityState([
            'data' => [
                'id_media_object' => 3509653,
                'leistungen' => [
                    ['code_ibe' => '99999', 'new_state' => 0, 'crs_status' => 'ausgebucht'],
                ],
            ],
        ]);
        $this->assertTrue($result['success']);
        $this->assertFalse($result['changed']);
        $this->assertSame(0, $result['changes_applied']);
    }

    public function testSyncAvailabilityStateSameStateNotUpdated(): void
    {
        $optionRow = new \stdClass();
        $optionRow->id = 'opt_sync_1';
        $optionRow->id_media_object = 3509653;
        $optionRow->id_booking_package = 'bp_1';
        $optionRow->state = 3;
        $optionRow->name = 'Doppelzimmer';
        $optionRow->code_ibe = '63095';

        $db = $this->createCustomMockDb([
            'fetchAllCallback' => function ($query) use ($optionRow) {
                if (strpos($query, 'pmt2core_touristic_options') !== false) {
                    return [$optionRow];
                }
                return [];
            },
        ]);
        Registry::getInstance()->add('db', $db);

        $controller = new Ibe();
        $result = $controller->syncAvailabilityState([
            'data' => [
                'id_media_object' => 3509653,
                'leistungen' => [
                    ['code_ibe' => '63095', 'new_state' => 3, 'crs_status' => 'buchbar'],
                ],
            ],
        ]);
        $this->assertTrue($result['success']);
        $this->assertFalse($result['changed']);
        $this->assertSame(0, $result['changes_applied']);
        $this->assertEmpty($result['applied_changes']);
    }

    public function testSyncAvailabilityStateValidChangeWithMockDb(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data']['search_mongodb']['enabled'] = false;
        Registry::getInstance()->add('config', $config);

        $optionRow = new \stdClass();
        $optionRow->id = 'opt_sync_1';
        $optionRow->id_media_object = 3509653;
        $optionRow->id_booking_package = 'bp_1';
        $optionRow->state = 3;
        $optionRow->name = 'Doppelzimmer DU/WC';
        $optionRow->code_ibe = '63095';

        $db = $this->createCustomMockDb([
            'fetchRowCallback' => function ($query) use ($optionRow) {
                if (strpos($query, 'pmt2core_touristic_options') !== false) {
                    return $optionRow;
                }
                return null;
            },
            'fetchAllCallback' => function ($query) use ($optionRow) {
                if (strpos($query, 'pmt2core_touristic_options') !== false && strpos($query, 'id_media_object') !== false) {
                    return [$optionRow];
                }
                return [];
            },
        ]);
        Registry::getInstance()->add('db', $db);

        $controller = new Ibe();
        $result = $controller->syncAvailabilityState([
            'data' => [
                'id_media_object' => 3509653,
                'id_booking_package' => 'bp_1',
                'leistungen' => [
                    ['code_ibe' => '63095', 'new_state' => 0, 'crs_status' => 'ausgebucht', 'option_name' => 'Doppelzimmer DU/WC'],
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['changed']);
        $this->assertSame(1, $result['changes_applied']);
        $this->assertCount(1, $result['applied_changes']);
        $this->assertSame('opt_sync_1', $result['applied_changes'][0]['id_option']);
        $this->assertSame('63095', $result['applied_changes'][0]['code_ibe']);
        $this->assertSame(3, $result['applied_changes'][0]['state_before']);
        $this->assertSame(0, $result['applied_changes'][0]['new_state']);
        $this->assertSame('ausgebucht', $result['applied_changes'][0]['crs_status']);
    }

    public function testSyncAvailabilityStateWithoutBookingPackageScopesGlobally(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data']['search_mongodb']['enabled'] = false;
        Registry::getInstance()->add('config', $config);

        $opt1 = new \stdClass();
        $opt1->id = 'opt_bp1';
        $opt1->state = 3;
        $opt1->name = 'DZ';
        $opt1->code_ibe = '62306';

        $opt2 = new \stdClass();
        $opt2->id = 'opt_bp2';
        $opt2->state = 3;
        $opt2->name = 'DZ';
        $opt2->code_ibe = '62306';

        $db = $this->createCustomMockDb([
            'fetchRowCallback' => function ($query) use ($opt1) {
                if (strpos($query, 'pmt2core_touristic_options') !== false) {
                    return $opt1;
                }
                return null;
            },
            'fetchAllCallback' => function ($query) use ($opt1, $opt2) {
                if (strpos($query, 'pmt2core_touristic_options') !== false) {
                    return [$opt1, $opt2];
                }
                return [];
            },
        ]);
        Registry::getInstance()->add('db', $db);

        $controller = new Ibe();
        $result = $controller->syncAvailabilityState([
            'data' => [
                'id_media_object' => 100,
                'leistungen' => [
                    ['code_ibe' => '62306', 'new_state' => 0, 'crs_status' => 'ausgebucht'],
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['changes_applied']);
    }

    public function testSyncAvailabilityStateGroupedCodeIbeWorstStateWins(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data']['search_mongodb']['enabled'] = false;
        Registry::getInstance()->add('config', $config);

        $groupedOpt = new \stdClass();
        $groupedOpt->id = 'opt_grouped';
        $groupedOpt->state = 3;
        $groupedOpt->name = 'Fähre + Hotel Kombi';
        $groupedOpt->code_ibe = '#62536#62538#62541#';

        $db = $this->createCustomMockDb([
            'fetchRowCallback' => function ($query) use ($groupedOpt) {
                if (strpos($query, 'pmt2core_touristic_options') !== false) {
                    return $groupedOpt;
                }
                return null;
            },
            'fetchAllCallback' => function ($query) use ($groupedOpt) {
                if (strpos($query, 'pmt2core_touristic_options') !== false) {
                    return [$groupedOpt];
                }
                return [];
            },
        ]);
        Registry::getInstance()->add('db', $db);

        $controller = new Ibe();
        $result = $controller->syncAvailabilityState([
            'data' => [
                'id_media_object' => 100,
                'id_booking_package' => 'bp_1',
                'leistungen' => [
                    ['code_ibe' => '62536', 'new_state' => 3, 'crs_status' => 'buchbar', 'option_name' => 'Fähre'],
                    ['code_ibe' => '62538', 'new_state' => 1, 'crs_status' => 'ausgebucht', 'option_name' => 'Hotel A'],
                    ['code_ibe' => '62541', 'new_state' => 3, 'crs_status' => 'buchbar', 'option_name' => 'Hotel B'],
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['changed']);
        $this->assertSame(1, $result['changes_applied']);
        $this->assertSame('opt_grouped', $result['applied_changes'][0]['id_option']);
        $this->assertSame('#62536#62538#62541#', $result['applied_changes'][0]['code_ibe']);
        $this->assertSame(3, $result['applied_changes'][0]['state_before']);
        $this->assertSame(1, $result['applied_changes'][0]['new_state']);
    }

    public function testSyncAvailabilityStateGroupedAllBuchbarNoChange(): void
    {
        $groupedOpt = new \stdClass();
        $groupedOpt->id = 'opt_grouped2';
        $groupedOpt->state = 3;
        $groupedOpt->name = 'Kombi';
        $groupedOpt->code_ibe = '#100#200#';

        $db = $this->createCustomMockDb([
            'fetchAllCallback' => function ($query) use ($groupedOpt) {
                if (strpos($query, 'pmt2core_touristic_options') !== false) {
                    return [$groupedOpt];
                }
                return [];
            },
        ]);
        Registry::getInstance()->add('db', $db);

        $controller = new Ibe();
        $result = $controller->syncAvailabilityState([
            'data' => [
                'id_media_object' => 100,
                'leistungen' => [
                    ['code_ibe' => '100', 'new_state' => 3, 'crs_status' => 'buchbar'],
                    ['code_ibe' => '200', 'new_state' => 3, 'crs_status' => 'buchbar'],
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['changed']);
        $this->assertSame(0, $result['changes_applied']);
    }

    public function testSyncAvailabilityStateGroupedStoppOverridesAll(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data']['search_mongodb']['enabled'] = false;
        Registry::getInstance()->add('config', $config);

        $groupedOpt = new \stdClass();
        $groupedOpt->id = 'opt_stopp';
        $groupedOpt->state = 3;
        $groupedOpt->name = 'Kombi';
        $groupedOpt->code_ibe = '#300#400#';

        $db = $this->createCustomMockDb([
            'fetchRowCallback' => function ($query) use ($groupedOpt) {
                if (strpos($query, 'pmt2core_touristic_options') !== false) {
                    return $groupedOpt;
                }
                return null;
            },
            'fetchAllCallback' => function ($query) use ($groupedOpt) {
                if (strpos($query, 'pmt2core_touristic_options') !== false) {
                    return [$groupedOpt];
                }
                return [];
            },
        ]);
        Registry::getInstance()->add('db', $db);

        $controller = new Ibe();
        $result = $controller->syncAvailabilityState([
            'data' => [
                'id_media_object' => 100,
                'leistungen' => [
                    ['code_ibe' => '300', 'new_state' => 3, 'crs_status' => 'buchbar'],
                    ['code_ibe' => '400', 'new_state' => 4, 'crs_status' => 'stopp'],
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['changes_applied']);
        $this->assertSame(4, $result['applied_changes'][0]['new_state']);
        $this->assertSame('stopp', $result['applied_changes'][0]['crs_status']);
    }
}
