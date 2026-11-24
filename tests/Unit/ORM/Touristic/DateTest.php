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

    /**
     * Kneissl P1455 (AMIS #465788): rail, flight and the local round trip bus share one group,
     * only the bus requires a seat plan and it is not the first transport of the outward way.
     */
    public function testGetTransportPairsTakesSeatplanFromAnyTransportOfAGroup(): void
    {
        $group = 'Aufpreis AIRail ab/bis Linz und Flugtaxen ab/bis Wien';
        $date = new Date(null, false);
        $date->transports = [
            $this->createGroupTransport('rail', 1, 'BAH', '293723', '2027-04-16 06:00', $group, false),
            $this->createGroupTransport('bus', 1, 'BUS', '293730', '2027-04-16 14:00', 'Flugtaxen ab/bis Wien#' . $group, true),
            $this->createGroupTransport('flight', 1, 'FLUG', '293727', '2027-04-16 09:00', 'Flugtaxen ab/bis Wien#' . $group, false),
            $this->createGroupTransport('return', 2, 'FLUG', '293736', '2027-04-23 10:00', 'Flugtaxen ab/bis Wien#' . $group, false),
        ];

        $pairs = $date->getTransportPairs([0, 2, 3], ['bus', 'rail', 'return', 'flight'], [], null, false, null);
        $linz = array_values(array_filter($pairs, function ($pair) use ($group) {
            return $pair['way1']->transport_group === $group;
        }));

        $this->assertCount(1, $linz);
        $this->assertSame('293723#293727#293730', $linz[0]['way1']->code_ibe);
        $this->assertTrue($linz[0]['way1']->seatplan_required);
        $this->assertSame('293730', $linz[0]['way1']->seatplan_transport_code_ibe);
        $this->assertEmpty($linz[0]['way2']->seatplan_required);
        $this->assertNull($linz[0]['way2']->seatplan_transport_code_ibe);
    }

    private function createGroupTransport(string $id, int $way, string $type, string $code_ibe, string $from, string $group, bool $seatplan): Transport
    {
        $transport = new Transport(null, false);
        $transport->setId($id);
        $transport->way = $way;
        $transport->type = $type;
        $transport->code_ibe = $code_ibe;
        $transport->transport_date_from = new \DateTime($from);
        $transport->transport_date_to = new \DateTime($from);
        $transport->transport_group = $group;
        $transport->seatplan_required = $seatplan;
        $transport->state = 3;
        $transport->price = 0;
        $transport->dont_use_for_offers = false;
        $transport->agencies = null;
        return $transport;
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
