<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\ORM\Object\DataView;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for DataView ORM: instantiation, fromArray, toStdClass, getters.
 */
class DataViewTest extends AbstractTestCase
{
    public function testInstantiationWithoutId(): void
    {
        $dv = new DataView(null, false);
        $this->assertNull($dv->getId());
    }

    public function testGetDbTableName(): void
    {
        $dv = new DataView(null, false);
        $this->assertSame('pmt2core_pmt2core_data_views', $dv->getDbTableName());
    }

    public function testSetAndGetId(): void
    {
        $dv = new DataView(null, false);
        $dv->setId(100);
        $this->assertSame(100, $dv->getId());
    }

    public function testFromArrayPopulatesProperties(): void
    {
        $dv = new DataView(null, false);
        $dv->fromArray([
            'id' => 1,
            'active' => true,
            'name' => 'Test View',
        ]);
        $this->assertSame(1, $dv->id);
        $this->assertTrue($dv->active);
        $this->assertSame('Test View', $dv->name);
    }

    public function testToStdClassWithoutRelations(): void
    {
        $dv = new DataView(null, false);
        $dv->id = 2;
        $dv->active = false;
        $dv->name = 'Another';
        $std = $dv->toStdClass(false);
        $this->assertInstanceOf(\stdClass::class, $std);
        $this->assertSame(2, $std->id);
        $this->assertFalse($std->active);
        $this->assertSame('Another', $std->name);
    }

    public function testIsValid(): void
    {
        $dv = new DataView(null, false);
        $this->assertFalse($dv->isValid());
        $dv->setId(1);
        $this->assertTrue($dv->isValid());
    }
}
