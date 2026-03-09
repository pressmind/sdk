<?php

namespace Pressmind\Tests\Unit\ORM\Touristic;

use Pressmind\ORM\Object\Touristic\Booking;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Touristic\Booking ORM type (deprecated): instantiation, property handling, fromArray, toStdClass.
 */
class BookingTest extends AbstractTestCase
{
    public function testInstantiationWithoutId(): void
    {
        $booking = new Booking(null, false);
        $this->assertNull($booking->getId());
    }

    public function testGetDbTableName(): void
    {
        $booking = new Booking(null, false);
        $this->assertSame('pmt2core_pmt2core_touristic_bookings', $booking->getDbTableName());
    }

    public function testSetAndGetId(): void
    {
        $booking = new Booking(null, false);
        $booking->setId(42);
        $this->assertSame(42, $booking->getId());
    }

    public function testFromArrayPopulatesScalarProperties(): void
    {
        $booking = new Booking(null, false);
        $booking->fromArray([
            'id' => 1,
            'code' => 'BK-001',
            'travel_name' => 'Test Travel',
            'name' => 'John',
            'surname' => 'Doe',
        ]);
        $this->assertSame(1, $booking->id);
        $this->assertSame('BK-001', $booking->code);
        $this->assertSame('Test Travel', $booking->travel_name);
        $this->assertSame('John', $booking->name);
        $this->assertSame('Doe', $booking->surname);
    }

    public function testToStdClassWithoutRelations(): void
    {
        $booking = new Booking(null, false);
        $booking->id = 2;
        $booking->code = 'BK-002';
        $booking->travel_name = 'Another';
        $std = $booking->toStdClass(false);
        $this->assertInstanceOf(\stdClass::class, $std);
        $this->assertSame(2, $std->id);
        $this->assertSame('BK-002', $std->code);
        $this->assertSame('Another', $std->travel_name);
    }
}
