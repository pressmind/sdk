<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Touristic\EarlyBirdDiscountGroup\Item;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Tests the actual EarlyBird discount calculation logic in MediaObject using Reflection.
 * Verifies bcmul/bcdiv precision for percentage and fixed discount.
 */
class EarlyBirdCalculationTest extends AbstractTestCase
{
    private function createDiscountItem(string $type, float $discountValue): Item
    {
        $item = new Item(null, false);
        $item->type = $type;
        $item->discount_value = $discountValue;
        return $item;
    }

    private function invokePercentageDiscount(float $totalPrice, float $percent): float
    {
        $mo = new MediaObject(null, false);
        $discount = $this->createDiscountItem('P', $percent);
        $method = new \ReflectionMethod(MediaObject::class, '_calculatePercentageEarlyBirdDiscount');
        $method->setAccessible(true);
        return (float) $method->invoke($mo, $discount, $totalPrice);
    }

    private function invokeFixedDiscount(float $discountValue): float
    {
        $mo = new MediaObject(null, false);
        $discount = $this->createDiscountItem('F', $discountValue);
        $method = new \ReflectionMethod(MediaObject::class, '_calculateFixedEarlyBirdDiscount');
        $method->setAccessible(true);
        return (float) $method->invoke($mo, $discount);
    }

    public function testPercentageDiscountThousandTenPercent(): void
    {
        $result = $this->invokePercentageDiscount(1000.0, 10.0);
        $this->assertSame(-100.0, $result, '1000 * 10% must be -100.00 (negative for discount)');
    }

    public function testPercentageDiscount999SevenPercent(): void
    {
        $result = $this->invokePercentageDiscount(999.0, 7.0);
        $this->assertEqualsWithDelta(-69.93, $result, 0.01, '999 * 7% must be -69.93 (bcdiv/bcmul rounding)');
    }

    public function testPercentageDiscountSmallAmount(): void
    {
        $result = $this->invokePercentageDiscount(0.01, 50.0);
        $this->assertLessThanOrEqual(0, $result);
        // bcmul(..., 2) rounds to 2 decimals, so 0.005 becomes 0.00
        $this->assertEqualsWithDelta(0, $result, 0.01, '0.01 * 50% rounds to zero with 2 decimal precision');
    }

    public function testPercentageDiscountZeroPercent(): void
    {
        $result = $this->invokePercentageDiscount(500.0, 0.0);
        $this->assertSame(0.0, $result);
    }

    public function testFixedDiscount75(): void
    {
        $result = $this->invokeFixedDiscount(75.0);
        $this->assertSame(-75.0, $result);
    }

    public function testFixedDiscountZero(): void
    {
        $result = $this->invokeFixedDiscount(0.0);
        $this->assertSame(0.0, $result);
    }

    public function testFixedDiscountSmallValue(): void
    {
        $result = $this->invokeFixedDiscount(0.01);
        $this->assertSame(-0.01, $result);
    }

    /**
     * Verify price_total = price_regular_before_discount + discount (discount is negative).
     */
    public function testPercentageResultIsNegative(): void
    {
        $regular = 1000.0;
        $discount = $this->invokePercentageDiscount($regular, 15.0);
        $this->assertLessThan(0, $discount);
        $total = $regular + $discount;
        $this->assertEqualsWithDelta(850.0, $total, 0.01);
    }
}
