<?php

namespace Pressmind\Tests\Unit\Search;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\CheapestPrice;

class CheapestPriceTest extends TestCase
{
    private array $getBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->getBackup = $_GET;
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = $this->getBackup;
        parent::tearDown();
    }

    public function testConstantValues(): void
    {
        $this->assertSame(3, CheapestPrice::STATE_BOOKABLE);
        $this->assertSame(1, CheapestPrice::STATE_REQUEST);
        $this->assertSame(5, CheapestPrice::STATE_STOP);
        $this->assertSame(
            [CheapestPrice::STATE_BOOKABLE, CheapestPrice::STATE_REQUEST, CheapestPrice::STATE_STOP],
            CheapestPrice::STATE_ORDER_BY_PRIO
        );
    }

    public function testDefaultPropertyValues(): void
    {
        $cp = new CheapestPrice();
        $this->assertNull($cp->id);
        $this->assertNull($cp->duration_from);
        $this->assertNull($cp->duration_to);
        $this->assertSame([], $cp->occupancies);
        $this->assertFalse($cp->occupancies_disable_fallback);
        $this->assertNull($cp->date_from);
        $this->assertNull($cp->date_to);
        $this->assertNull($cp->price_from);
        $this->assertNull($cp->price_to);
        $this->assertNull($cp->id_date);
        $this->assertNull($cp->id_option);
        $this->assertNull($cp->id_booking_package);
        $this->assertNull($cp->id_housing_package);
        $this->assertNull($cp->housing_package_code_ibe);
        $this->assertSame([], $cp->transport_types);
        $this->assertSame([], $cp->transport_1_airport);
        $this->assertNull($cp->origin);
        $this->assertNull($cp->agency);
        $this->assertSame(CheapestPrice::STATE_BOOKABLE, $cp->state);
        $this->assertNull($cp->id_startingpoint_option);
        $this->assertNull($cp->startingpoint_option_name);
        $this->assertNull($cp->startingpoint_id_city);
        $this->assertNull($cp->housing_package_id_name);
        $this->assertSame(
            [CheapestPrice::STATE_BOOKABLE, CheapestPrice::STATE_REQUEST, CheapestPrice::STATE_STOP],
            $cp->state_fallback_order
        );
    }

    public function testInitFromGetWithEmptyGetLeavesDefaults(): void
    {
        $cp = new CheapestPrice();
        $cp->initFromGet();

        $this->assertNull($cp->id);
        $this->assertNull($cp->duration_from);
        $this->assertSame([], $cp->occupancies);
        $this->assertFalse($cp->occupancies_disable_fallback);
        $this->assertSame(CheapestPrice::STATE_BOOKABLE, $cp->state);
    }

    public function testInitFromGetParsesOccupanciesAsArray(): void
    {
        $_GET = ['occupancies' => '2,3,4'];
        $cp = new CheapestPrice();
        $cp->initFromGet();
        $this->assertSame(['2', '3', '4'], $cp->occupancies);
    }

    public function testInitFromGetParsesTransportTypesAsArray(): void
    {
        $_GET = ['transport_types' => 'BUS,FLUG,BAHN'];
        $cp = new CheapestPrice();
        $cp->initFromGet();
        $this->assertSame(['BUS', 'FLUG', 'BAHN'], $cp->transport_types);
    }

    public function testInitFromGetParsesTransport1AirportAsArray(): void
    {
        $_GET = ['transport_1_airport' => 'FRA,MUC'];
        $cp = new CheapestPrice();
        $cp->initFromGet();
        $this->assertSame(['FRA', 'MUC'], $cp->transport_1_airport);
    }

    public function testInitFromGetParsesOccupanciesDisableFallbackAsBool(): void
    {
        $_GET = ['occupancies_disable_fallback' => '1'];
        $cp = new CheapestPrice();
        $cp->initFromGet();
        $this->assertTrue($cp->occupancies_disable_fallback);

        $_GET = ['occupancies_disable_fallback' => '0'];
        $cp2 = new CheapestPrice();
        $cp2->initFromGet();
        // empty('0') is true in PHP, so '0' is skipped; default (false) remains
        $this->assertFalse($cp2->occupancies_disable_fallback);
    }

    public function testInitFromGetParsesStateAsInt(): void
    {
        $_GET = ['state' => '5'];
        $cp = new CheapestPrice();
        $cp->initFromGet();
        $this->assertSame(5, $cp->state);
    }

    public function testInitFromGetStateZeroBecomesNull(): void
    {
        // empty('0') is true in PHP, so state '0' is never processed; default STATE_BOOKABLE remains
        $_GET = ['state' => '0'];
        $cp = new CheapestPrice();
        $cp->initFromGet();
        $this->assertSame(CheapestPrice::STATE_BOOKABLE, $cp->state);
    }

    public function testInitFromGetParsesPriceAsFloat(): void
    {
        $_GET = ['price_from' => '99.50', 'price_to' => '1200'];
        $cp = new CheapestPrice();
        $cp->initFromGet();
        $this->assertSame(99.5, $cp->price_from);
        $this->assertSame(1200.0, $cp->price_to);
    }

    public function testInitFromGetParsesDurationAsInt(): void
    {
        $_GET = ['duration_from' => '3', 'duration_to' => '21'];
        $cp = new CheapestPrice();
        $cp->initFromGet();
        $this->assertSame(3, $cp->duration_from);
        $this->assertSame(21, $cp->duration_to);
    }

    public function testInitFromGetParsesDateAsDateTime(): void
    {
        $_GET = ['date_from' => '20260715', 'date_to' => '20260831'];
        $cp = new CheapestPrice();
        $cp->initFromGet();
        $this->assertInstanceOf(\DateTime::class, $cp->date_from);
        $this->assertInstanceOf(\DateTime::class, $cp->date_to);
        $this->assertSame('2026-07-15', $cp->date_from->format('Y-m-d'));
        $this->assertSame('2026-08-31', $cp->date_to->format('Y-m-d'));
    }

    public function testInitFromGetSanitizesStringParams(): void
    {
        $_GET = [
            'id' => '123<script>',
            'agency' => 'agency&foo=bar',
            'origin' => 'test value!@#',
        ];
        $cp = new CheapestPrice();
        $cp->initFromGet();
        $this->assertSame('123script', $cp->id);
        $this->assertSame('agencyfoobar', $cp->agency);
        $this->assertSame('testvalue', $cp->origin);
    }
}
