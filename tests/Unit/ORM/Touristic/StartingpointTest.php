<?php

namespace Pressmind\Tests\Unit\ORM\Touristic;

use Pressmind\ORM\Object\Touristic\Startingpoint;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Touristic\Startingpoint ORM type: instantiation, property handling, fromArray, toStdClass.
 */
class StartingpointTest extends AbstractTestCase
{
    public function testInstantiationWithoutId(): void
    {
        $sp = new Startingpoint(null, false);
        $this->assertNull($sp->getId());
    }

    public function testGetDbTableName(): void
    {
        $sp = new Startingpoint(null, false);
        $this->assertSame('pmt2core_pmt2core_touristic_startingpoints', $sp->getDbTableName());
    }

    public function testSetAndGetId(): void
    {
        $sp = new Startingpoint(null, false);
        $sp->setId('sp-001');
        $this->assertSame('sp-001', $sp->getId());
    }

    public function testFromArrayPopulatesScalarProperties(): void
    {
        $sp = new Startingpoint(null, false);
        $sp->fromArray([
            'id' => 'sp-1',
            'code' => 'BER',
            'name' => 'Berlin Central',
            'text' => 'Departure from Berlin',
        ]);
        $this->assertSame('sp-1', $sp->id);
        $this->assertSame('BER', $sp->code);
        $this->assertSame('Berlin Central', $sp->name);
        $this->assertSame('Departure from Berlin', $sp->text);
    }

    public function testToStdClassWithoutRelations(): void
    {
        $sp = new Startingpoint(null, false);
        $sp->id = 'sp-2';
        $sp->code = 'MUC';
        $sp->name = 'Munich';
        $std = $sp->toStdClass(false);
        $this->assertInstanceOf(\stdClass::class, $std);
        $this->assertSame('sp-2', $std->id);
        $this->assertSame('MUC', $std->code);
        $this->assertSame('Munich', $std->name);
    }
}
