<?php

namespace Pressmind\Tests\Unit\ORM\Touristic\Housing;

use Pressmind\ORM\Object\Touristic\Housing\Package;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Touristic\Housing\Package ORM type: instantiation, property handling, fromArray, toStdClass.
 */
class PackageTest extends AbstractTestCase
{
    public function testInstantiationWithoutId(): void
    {
        $pkg = new Package(null, false);
        $this->assertNull($pkg->getId());
    }

    public function testGetDbTableName(): void
    {
        $pkg = new Package(null, false);
        $this->assertSame('pmt2core_pmt2core_touristic_housing_packages', $pkg->getDbTableName());
    }

    public function testSetAndGetId(): void
    {
        $pkg = new Package(null, false);
        $pkg->setId('hp-001');
        $this->assertSame('hp-001', $pkg->getId());
    }

    public function testFromArrayPopulatesScalarProperties(): void
    {
        $pkg = new Package(null, false);
        $pkg->fromArray([
            'id' => 'hp-1',
            'id_media_object' => 100,
            'id_booking_package' => 'bp-1',
            'name' => 'Half Board',
            'code' => 'HB',
            'nights' => 7,
        ]);
        $this->assertSame('hp-1', $pkg->id);
        $this->assertSame(100, $pkg->id_media_object);
        $this->assertSame('bp-1', $pkg->id_booking_package);
        $this->assertSame('Half Board', $pkg->name);
        $this->assertSame('HB', $pkg->code);
        $this->assertSame(7, $pkg->nights);
    }

    public function testToStdClassWithoutRelations(): void
    {
        $pkg = new Package(null, false);
        $pkg->id = 'hp-2';
        $pkg->id_media_object = 200;
        $pkg->name = 'Full Board';
        $pkg->code = 'FB';
        $std = $pkg->toStdClass(false);
        $this->assertInstanceOf(\stdClass::class, $std);
        $this->assertSame('hp-2', $std->id);
        $this->assertSame(200, $std->id_media_object);
        $this->assertSame('Full Board', $std->name);
        $this->assertSame('FB', $std->code);
    }
}
