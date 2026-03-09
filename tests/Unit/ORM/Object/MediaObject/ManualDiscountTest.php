<?php

namespace Pressmind\Tests\Unit\ORM\Object\MediaObject;

use Pressmind\ORM\Object\MediaObject\ManualDiscount;
use Pressmind\Tests\Unit\AbstractTestCase;

class ManualDiscountTest extends AbstractTestCase
{
    public function testDeleteByMediaObjectIdCallsDbDelete(): void
    {
        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
        $db->method('fetchAll')->willReturn([]);
        $db->method('fetchRow')->willReturn(null);

        $db->expects($this->once())
            ->method('delete')
            ->with(
                $this->stringContains('pmt2core_media_object_manual_discounts'),
                $this->equalTo(['id_media_object = ?', 42])
            );

        \Pressmind\Registry::getInstance()->add('db', $db, true);

        $discount = new ManualDiscount(null, false);
        $discount->deleteByMediaObjectId(42);
    }

    public function testTableNameIsPmt2coreMediaObjectManualDiscounts(): void
    {
        $discount = new ManualDiscount(null, false);
        $this->assertStringContainsString(
            'pmt2core_media_object_manual_discounts',
            $discount->getDbTableName()
        );
    }

    public function testPropertyDefinitionsContainRequiredFields(): void
    {
        $discount = new ManualDiscount(null, false);
        $properties = $discount->getPropertyDefinitions();

        $requiredFields = ['id', 'id_media_object', 'value', 'type'];
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $properties, "Property '$field' must exist");
            $this->assertTrue($properties[$field]['required'], "Property '$field' must be required");
        }
    }

    public function testTypeValidatorAllowsFixedPriceAndPercent(): void
    {
        $discount = new ManualDiscount(null, false);
        $definitions = $discount->getPropertyDefinitions();
        $typeValidators = $definitions['type']['validators'];

        $inarrayValidator = null;
        foreach ($typeValidators as $validator) {
            if ($validator['name'] === 'inarray') {
                $inarrayValidator = $validator;
                break;
            }
        }

        $this->assertNotNull($inarrayValidator, 'inarray validator must be defined on type');
        $this->assertContains('fixed_price', $inarrayValidator['params']);
        $this->assertContains('percent', $inarrayValidator['params']);
    }
}
