<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use DateTime;
use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Touristic\Booking\Package;
use Pressmind\ORM\Object\Touristic\Date;
use Pressmind\ORM\Object\Touristic\Transport;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;
use stdClass;

/**
 * Guards data.touristic.allow_zero_prices.
 *
 * The booking package mirrors a free shuttle trip as delivered by the Kuschick import:
 * price_mix = date_transport, one date, outward and return transport both at 0.
 * The booking package is assigned in memory, the mocked database only records the
 * cheapest price rows that insertCheapestPrice() writes.
 */
class MediaObjectAllowZeroPricesTest extends AbstractTestCase
{
    private const SETTING = 'allow_zero_prices';

    /**
     * @var array[] rows passed to batchInsert() for pmt2core_cheapest_price_speed
     */
    private $insertedRows = [];

    /**
     * @var string[] queries passed to fetchAll() for pmt2core_cheapest_price_speed
     */
    private $cheapestPriceQueries = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->insertedRows = [];
        $this->cheapestPriceQueries = [];
        Registry::getInstance()->add('db', $this->createRecordingDb());
    }

    private function sdkRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    private function setAllowZeroPrices(bool $allow): void
    {
        Registry::getInstance()->add('config', $this->createMockConfig([
            'data' => ['touristic' => [self::SETTING => $allow]],
        ]));
    }

    private function createRecordingDb(): AdapterInterface
    {
        $db = $this->createMock(AdapterInterface::class);
        $db->method('fetchRow')->willReturn(null);
        $db->method('fetchOne')->willReturn(null);
        $db->method('getAffectedRows')->willReturn(0);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
        $db->method('inTransaction')->willReturn(false);
        $db->method('delete')->willReturn(null);
        $db->method('fetchAll')->willReturnCallback(function ($query) {
            if (strpos($query, 'cheapest_price_speed') === false) {
                return [];
            }
            $this->cheapestPriceQueries[] = $query;
            // getCheapestPricesOptions() expects at least one aggregated row
            if (strpos($query, 'GROUP BY') !== false) {
                return [$this->createOptionsRow()];
            }
            return [];
        });
        $db->method('batchInsert')->willReturnCallback(function ($table, $columns, $rows) {
            if (strpos($table, 'cheapest_price_speed') !== false) {
                foreach ($rows as $row) {
                    $this->insertedRows[] = array_combine($columns, $row);
                }
            }
            return count($rows);
        });
        return $db;
    }

    private function createOptionsRow(): stdClass
    {
        $departure = (new DateTime('+30 days'))->format('Y-m-d 00:00:00');
        $row = new stdClass();
        $row->duration = 1;
        $row->transport_type = 'BUS';
        $row->transport_1_airport_name = null;
        $row->transport_1_airport = null;
        $row->option_occupancy = 2;
        $row->date_departure_from = $departure;
        $row->date_departure_to = $departure;
        $row->count = 1;
        return $row;
    }

    private function createTransport(string $id, int $way, float $price): Transport
    {
        $transport = new Transport();
        $transport->id = $id;
        $transport->id_date = 'date-1';
        $transport->id_media_object = 100;
        $transport->id_booking_package = 'bp-1';
        $transport->type = 'BUS';
        $transport->way = $way;
        $transport->price = $price;
        $transport->state = 3;
        $transport->quota = 99;
        $transport->transport_group = 'Busreise';
        $transport->dont_use_for_offers = false;
        $transport->use_earlybird = false;
        return $transport;
    }

    private function createMediaObjectWithTransportPrices(float $outward, float $return): MediaObject
    {
        $departure = new DateTime('+30 days');
        $departure->setTime(0, 0, 0);

        $date = new Date();
        $date->id = 'date-1';
        $date->id_media_object = 100;
        $date->id_booking_package = 'bp-1';
        $date->departure = $departure;
        $date->arrival = clone $departure;
        $date->state = 1;
        $date->season = 'A';
        $date->transports = [
            $this->createTransport('tr-1', 1, $outward),
            $this->createTransport('tr-2', 2, $return),
        ];

        $package = new Package();
        $package->id = 'bp-1';
        $package->id_media_object = 100;
        $package->name = 'Free shuttle';
        $package->duration = 1;
        $package->price_mix = 'date_transport';
        $package->dates = [$date];

        $mo = new MediaObject(null, false);
        $mo->setId(100);
        $mo->booking_packages = [$package];
        return $mo;
    }

    public function testDefaultConfigShipsTheSettingDisabled(): void
    {
        $config = json_decode(file_get_contents($this->sdkRoot() . '/config.default.json'), true);
        $touristic = $config['development']['data']['touristic'] ?? null;
        $this->assertIsArray($touristic, 'development.data.touristic is missing');
        $this->assertArrayHasKey(self::SETTING, $touristic, 'The setting is missing from config.default.json');
        $this->assertFalse($touristic[self::SETTING], 'The default has to keep the existing behaviour');
    }

    public function testZeroPricedOfferIsSkippedByDefault(): void
    {
        $this->createMediaObjectWithTransportPrices(0, 0)->insertCheapestPrice();
        $this->assertCount(0, $this->insertedRows);
    }

    public function testZeroPricedOfferIsWrittenWhenAllowed(): void
    {
        $this->setAllowZeroPrices(true);
        $this->createMediaObjectWithTransportPrices(0, 0)->insertCheapestPrice();
        $this->assertCount(1, $this->insertedRows);
        $this->assertEquals(0, $this->insertedRows[0]['price_total']);
        $this->assertSame('date-1', $this->insertedRows[0]['id_date']);
    }

    public function testPricedOfferIsWrittenInBothModes(): void
    {
        $this->createMediaObjectWithTransportPrices(15, 15)->insertCheapestPrice();
        $this->setAllowZeroPrices(true);
        $this->createMediaObjectWithTransportPrices(15, 15)->insertCheapestPrice();
        $this->assertCount(2, $this->insertedRows);
        $this->assertEquals(30, $this->insertedRows[0]['price_total']);
        $this->assertEquals(30, $this->insertedRows[1]['price_total']);
    }

    public function testNegativeOfferStaysExcludedWhenAllowed(): void
    {
        $this->setAllowZeroPrices(true);
        $this->createMediaObjectWithTransportPrices(-10, 0)->insertCheapestPrice();
        $this->assertCount(0, $this->insertedRows);
    }

    public function testCheapestPriceQueriesExcludeZeroPricesByDefault(): void
    {
        $mo = new MediaObject(null, false);
        $mo->setId(100);
        $mo->getCheapestPrices();
        $mo->getCheapestPricesOptions();
        $this->assertCount(2, $this->cheapestPriceQueries);
        foreach ($this->cheapestPriceQueries as $query) {
            $this->assertStringContainsString('price_total > 0', $query);
        }
    }

    public function testCheapestPriceQueriesIncludeZeroPricesWhenAllowed(): void
    {
        $this->setAllowZeroPrices(true);
        $mo = new MediaObject(null, false);
        $mo->setId(100);
        $mo->getCheapestPrices();
        $mo->getCheapestPricesOptions();
        $this->assertCount(2, $this->cheapestPriceQueries);
        foreach ($this->cheapestPriceQueries as $query) {
            $this->assertStringContainsString('price_total >= 0', $query);
        }
    }
}
