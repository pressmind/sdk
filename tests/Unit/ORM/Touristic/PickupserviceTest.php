<?php

namespace Pressmind\Tests\Unit\ORM\Touristic;

use Pressmind\ORM\Object\Touristic\Pickupservice;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Touristic\Pickupservice ORM type (deprecated): instantiation, property handling, fromArray, toStdClass.
 */
class PickupserviceTest extends AbstractTestCase
{
    public function testInstantiationWithoutId(): void
    {
        $ps = new Pickupservice(null, false);
        $this->assertNull($ps->getId());
    }

    public function testGetDbTableName(): void
    {
        $ps = new Pickupservice(null, false);
        $this->assertSame('pmt2core_pmt2core_touristic_pickupservices', $ps->getDbTableName());
    }

    public function testSetAndGetId(): void
    {
        $ps = new Pickupservice(null, false);
        $ps->setId('ps-001');
        $this->assertSame('ps-001', $ps->getId());
    }

    public function testFromArrayPopulatesScalarProperties(): void
    {
        $ps = new Pickupservice(null, false);
        $ps->fromArray([
            'id' => 'ps-1',
            'code' => 'PICK01',
            'name' => 'Hotel Pickup',
            'text' => 'Pickup from hotel',
        ]);
        $this->assertSame('ps-1', $ps->id);
        $this->assertSame('PICK01', $ps->code);
        $this->assertSame('Hotel Pickup', $ps->name);
        $this->assertSame('Pickup from hotel', $ps->text);
    }

    public function testToStdClassWithoutRelations(): void
    {
        $ps = new Pickupservice(null, false);
        $ps->id = 'ps-2';
        $ps->code = 'PICK02';
        $ps->name = 'Airport Pickup';
        $ps->text = 'Pickup at airport';
        $std = $ps->toStdClass(false);
        $this->assertInstanceOf(\stdClass::class, $std);
        $this->assertSame('ps-2', $std->id);
        $this->assertSame('PICK02', $std->code);
        $this->assertSame('Airport Pickup', $std->name);
        $this->assertSame('Pickup at airport', $std->text);
    }
}
