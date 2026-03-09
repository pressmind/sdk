<?php

namespace Pressmind\Tests\Unit\ORM\Touristic;

use Pressmind\ORM\Object\Touristic\Option;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Touristic\Option ORM type: instantiation, property handling, fromArray, toStdClass.
 */
class OptionTest extends AbstractTestCase
{
    public function testInstantiationWithoutId(): void
    {
        $option = new Option(null, false);
        $this->assertNull($option->getId());
    }

    public function testGetDbTableName(): void
    {
        $option = new Option(null, false);
        $this->assertSame('pmt2core_pmt2core_touristic_options', $option->getDbTableName());
    }

    public function testSetAndGetId(): void
    {
        $option = new Option(null, false);
        $option->setId('opt-001');
        $this->assertSame('opt-001', $option->getId());
    }

    public function testFromArrayPopulatesScalarProperties(): void
    {
        $option = new Option(null, false);
        $option->fromArray([
            'id' => 'opt-1',
            'id_media_object' => 100,
            'id_booking_package' => 'bp-1',
            'id_housing_package' => 'hp-1',
            'type' => 'housing_option',
            'code' => 'CODE01',
            'name' => 'Test Option',
            'price' => 99.50,
            'occupancy' => 2,
            'state' => 3,
        ]);
        $this->assertSame('opt-1', $option->id);
        $this->assertSame(100, $option->id_media_object);
        $this->assertSame('bp-1', $option->id_booking_package);
        $this->assertSame('housing_option', $option->type);
        $this->assertSame('CODE01', $option->code);
        $this->assertSame('Test Option', $option->name);
        $this->assertSame(99.50, $option->price);
        $this->assertSame(2, $option->occupancy);
        $this->assertSame(3, $option->state);
    }

    public function testToStdClassWithoutRelations(): void
    {
        $option = new Option(null, false);
        $option->id = 'opt-2';
        $option->id_media_object = 200;
        $option->code = 'C2';
        $option->name = 'Option Two';
        $option->price = 150.0;
        $std = $option->toStdClass(false);
        $this->assertInstanceOf(\stdClass::class, $std);
        $this->assertSame('opt-2', $std->id);
        $this->assertSame(200, $std->id_media_object);
        $this->assertSame('C2', $std->code);
        $this->assertSame('Option Two', $std->name);
        $this->assertSame(150.0, $std->price);
    }
}
