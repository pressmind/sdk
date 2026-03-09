<?php

namespace Pressmind\Tests\Unit\ORM\Touristic;

use Pressmind\ORM\Object\Touristic\EarlyPaymentDiscountGroup;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Touristic\EarlyPaymentDiscountGroup ORM type: instantiation, property handling, fromArray, toStdClass.
 * Does NOT call removeOrphans() (runs SQL).
 */
class EarlyPaymentDiscountGroupTest extends AbstractTestCase
{
    public function testInstantiationWithoutId(): void
    {
        $group = new EarlyPaymentDiscountGroup(null, false);
        $this->assertNull($group->getId());
    }

    public function testGetDbTableName(): void
    {
        $group = new EarlyPaymentDiscountGroup(null, false);
        $this->assertSame('pmt2core_pmt2core_touristic_early_payment_discount_group', $group->getDbTableName());
    }

    public function testSetAndGetId(): void
    {
        $group = new EarlyPaymentDiscountGroup(null, false);
        $group->setId('epdg-001');
        $this->assertSame('epdg-001', $group->getId());
    }

    public function testFromArrayPopulatesScalarProperties(): void
    {
        $group = new EarlyPaymentDiscountGroup(null, false);
        $group->fromArray([
            'id' => 'epdg-1',
            'name' => 'Early Pay 5%',
            'import_code' => 'EP5',
        ]);
        $this->assertSame('epdg-1', $group->id);
        $this->assertSame('Early Pay 5%', $group->name);
        $this->assertSame('EP5', $group->import_code);
    }

    public function testToStdClassWithoutRelations(): void
    {
        $group = new EarlyPaymentDiscountGroup(null, false);
        $group->id = 'epdg-2';
        $group->name = 'Early Pay 10%';
        $group->import_code = 'EP10';
        $std = $group->toStdClass(false);
        $this->assertInstanceOf(\stdClass::class, $std);
        $this->assertSame('epdg-2', $std->id);
        $this->assertSame('Early Pay 10%', $std->name);
        $this->assertSame('EP10', $std->import_code);
    }
}
