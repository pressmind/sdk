<?php

namespace Pressmind\Tests\Unit\ORM\Object\Touristic\Date;

use Pressmind\ORM\Object\Touristic\Date;
use Pressmind\ORM\Object\Touristic\Date\Attribute;
use Pressmind\ORM\Object\Touristic\Date\LoyaltyPoints;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Guards the loyalty points rule of a date as delivered by the Kuschick import.
 */
class LoyaltyPointsTest extends AbstractTestCase
{
    private const REFERENCE_FIELDS = ['id', 'id_media_object', 'id_booking_package', 'id_date'];

    public function testDefinitionMatchesDateAttributeLayout(): void
    {
        $loyaltyPoints = new LoyaltyPoints();
        $attribute = new Attribute();

        // the mocked adapter returns the table prefix 'pmt2core_'
        $this->assertSame('pmt2core_pmt2core_touristic_date_loyalty_points', $loyaltyPoints->getDbTableName());
        $this->assertSame('id', $loyaltyPoints->getDbPrimaryKey());
        $this->assertTrue($loyaltyPoints->dontUseAutoincrementOnPrimaryKey());

        foreach (self::REFERENCE_FIELDS as $field) {
            $this->assertSame($attribute->getPropertyDefinition($field), $loyaltyPoints->getPropertyDefinition($field), $field);
            $this->assertTrue($loyaltyPoints->getPropertyDefinition($field)['required'], $field);
        }
        foreach (['id_date', 'id_booking_package', 'id_media_object'] as $field) {
            $this->assertSame([$field => 'index'], $loyaltyPoints->getPropertyDefinition($field)['index'], $field);
        }

        $maxlengthOne = [['name' => 'maxlength', 'params' => 1]];
        $this->assertFieldDefinition($loyaltyPoints, 'recipient', 'string', $maxlengthOne);
        $this->assertFieldDefinition($loyaltyPoints, 'calculation', 'string', $maxlengthOne);
        $this->assertFieldDefinition($loyaltyPoints, 'points', 'integer', null);
        $this->assertFieldDefinition($loyaltyPoints, 'amount', 'float', null);
    }

    public function testDateFromStdClassBuildsLoyaltyPointsRelation(): void
    {
        $rule = (object) [
            'id' => 'lp-1',
            'id_date' => 'date-1',
            'id_booking_package' => 'bp-1',
            'id_media_object' => 100,
            'recipient' => 'T',
            'calculation' => 'U',
            'points' => 100,
            'amount' => 40.0,
        ];
        $date = new Date(null, false);

        $this->expectOutputString('');
        $date->fromStdClass((object) [
            'id' => 'date-1',
            'id_media_object' => 100,
            'id_booking_package' => 'bp-1',
            'code' => 'D1',
            'loyalty_points' => [$rule],
        ]);

        $this->assertSame('date-1', $date->id);
        $this->assertSame('D1', $date->code);
        $this->assertCount(1, $date->loyalty_points);
        $loyaltyPoints = $date->loyalty_points[0];
        $this->assertInstanceOf(LoyaltyPoints::class, $loyaltyPoints);
        $this->assertSame('lp-1', $loyaltyPoints->id);
        $this->assertSame('date-1', $loyaltyPoints->id_date);
        $this->assertSame('bp-1', $loyaltyPoints->id_booking_package);
        $this->assertSame(100, $loyaltyPoints->id_media_object);
        $this->assertSame('T', $loyaltyPoints->recipient);
        $this->assertSame('U', $loyaltyPoints->calculation);
        $this->assertSame(100, $loyaltyPoints->points);
        $this->assertSame(40.0, $loyaltyPoints->amount);
    }

    private function assertFieldDefinition(LoyaltyPoints $object, string $field, string $type, ?array $validators): void
    {
        $definition = $object->getPropertyDefinition($field);
        $this->assertSame($type, $definition['type'], $field);
        $this->assertFalse($definition['required'], $field);
        $this->assertSame($validators, $definition['validators'], $field);
    }
}
