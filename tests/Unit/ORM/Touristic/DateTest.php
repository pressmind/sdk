<?php

namespace Pressmind\Tests\Unit\ORM\Touristic;

use Pressmind\ORM\Object\Touristic\Date;
use Pressmind\ORM\Object\Touristic\Transport;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Touristic\Date ORM type: instantiation, property handling, fromArray, toStdClass,
 * getTransports, getTransportPairs, getSightseeings, getExtras, getTickets.
 */
class DateTest extends AbstractTestCase
{
    public function testInstantiationWithoutId(): void
    {
        $date = new Date(null, false);
        $this->assertNull($date->getId());
    }

    public function testGetDbTableName(): void
    {
        $date = new Date(null, false);
        $this->assertSame('pmt2core_pmt2core_touristic_dates', $date->getDbTableName());
    }

    public function testSetAndGetId(): void
    {
        $date = new Date(null, false);
        $date->setId('date-001');
        $this->assertSame('date-001', $date->getId());
    }

    public function testFromArrayPopulatesScalarProperties(): void
    {
        $date = new Date(null, false);
        $date->fromArray([
            'id' => 'dt-1',
            'id_media_object' => 100,
            'id_booking_package' => 'bp-1',
            'id_starting_point' => 'sp-1',
            'code' => 'CODE01',
            'season' => '2026',
            'pax_min' => 2,
            'pax_max' => 10,
        ]);
        $this->assertSame('dt-1', $date->id);
        $this->assertSame(100, $date->id_media_object);
        $this->assertSame('bp-1', $date->id_booking_package);
        $this->assertSame('sp-1', $date->id_starting_point);
        $this->assertSame('CODE01', $date->code);
        $this->assertSame('2026', $date->season);
        $this->assertSame(2, $date->pax_min);
        $this->assertSame(10, $date->pax_max);
    }

    public function testToStdClassWithoutRelations(): void
    {
        $date = new Date(null, false);
        $date->id = 'dt-2';
        $date->id_media_object = 200;
        $date->code = 'C2';
        $date->season = '2027';
        $std = $date->toStdClass(false);
        $this->assertInstanceOf(\stdClass::class, $std);
        $this->assertSame('dt-2', $std->id);
        $this->assertSame(200, $std->id_media_object);
        $this->assertSame('C2', $std->code);
        $this->assertSame('2027', $std->season);
    }

    public function testGetTransportsWithEmptyTransports(): void
    {
        $date = new Date(null, false);
        $date->transports = [];
        $transports = $date->getTransports();
        $this->assertIsArray($transports);
        $this->assertCount(0, $transports);
    }

    public function testGetTransportsWithOneTransport(): void
    {
        $date = new Date(null, false);
        $transport = new Transport(null, false);
        $transport->setId('t1');
        $transport->state = 2;
        $transport->type = 'BUS';
        $transport->dont_use_for_offers = false;
        $transport->agencies = null;
        $date->transports = [$transport];
        $transports = $date->getTransports([0, 2, 3], [], [], false, null);
        $this->assertCount(1, $transports);
        $this->assertSame('t1', $transports[0]->getId());
    }

    public function testGetTransportPairsWithEmptyTransports(): void
    {
        $date = new Date(null, false);
        $date->transports = [];
        $pairs = $date->getTransportPairs([0, 2, 3], [], [], null, false, null);
        $this->assertIsArray($pairs);
        $this->assertCount(0, $pairs);
    }

    public function testGetSightseeingsReturnsArray(): void
    {
        $date = new Date(null, false);
        $date->id_booking_package = 'bp-1';
        $date->season = '2026';
        $date->departure = new \DateTime('2026-01-15');
        $date->arrival = new \DateTime('2026-01-20');
        $options = $date->getSightseeings(false, null);
        $this->assertIsArray($options);
    }

    public function testGetExtrasReturnsArray(): void
    {
        $date = new Date(null, false);
        $date->id_booking_package = 'bp-1';
        $date->season = '2026';
        $date->departure = new \DateTime('2026-01-15');
        $date->arrival = new \DateTime('2026-01-20');
        $options = $date->getExtras(false, null);
        $this->assertIsArray($options);
    }

    public function testGetTicketsReturnsArray(): void
    {
        $date = new Date(null, false);
        $date->id_booking_package = 'bp-1';
        $date->season = '2026';
        $date->departure = new \DateTime('2026-01-15');
        $date->arrival = new \DateTime('2026-01-20');
        $options = $date->getTickets(false, null);
        $this->assertIsArray($options);
    }

    public function testGetHousingOptionsReturnsArray(): void
    {
        $date = new Date(null, false);
        $date->id_booking_package = 'bp-1';
        $date->season = '2026';
        $options = $date->getHousingOptions([0, 1, 2, 3], false, null);
        $this->assertIsArray($options);
    }
}
