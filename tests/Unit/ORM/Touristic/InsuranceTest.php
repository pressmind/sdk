<?php

namespace Pressmind\Tests\Unit\ORM\Touristic;

use Pressmind\ORM\Object\Touristic\Insurance;
use Pressmind\ORM\Object\Touristic\Insurance\Calculated;
use Pressmind\Tests\Unit\AbstractTestCase;

class InsuranceTest extends AbstractTestCase
{
    /**
     * When id is 0 (default "no insurance") the method returns a Calculated with price 0.
     */
    public function testIsAvailableForTravelDateAndPriceAndPersonAgeReturnsDefaultWhenIdZero(): void
    {
        $insurance = new Insurance(0, false);
        $insurance->name = 'No insurance';
        $insurance->active = true;
        $insurance->code = 'default';

        $dateStart = new \DateTime('2026-06-01');
        $dateEnd = new \DateTime('2026-06-08');
        $result = $insurance->isAvailableForTravelDateAndPriceAndPersonAge(
            $dateStart,
            $dateEnd,
            1000.0,
            7,
            30,
            2
        );

        $this->assertInstanceOf(Calculated::class, $result);
        $this->assertSame(0.0, $result->price);
        $this->assertSame('No insurance', $result->name);
        $this->assertSame('Default', $result->code);
    }

    /**
     * When insurance has id but no price_tables, returns false.
     */
    public function testIsAvailableReturnsFalseWhenNoMatchingPriceTable(): void
    {
        $insurance = new Insurance(null, false);
        $insurance->setId('ins-1');
        $insurance->name = 'Test Insurance';
        $insurance->active = true;
        $insurance->price_tables = [];

        $dateStart = new \DateTime('2026-06-01');
        $dateEnd = new \DateTime('2026-06-08');
        $result = $insurance->isAvailableForTravelDateAndPriceAndPersonAge(
            $dateStart,
            $dateEnd,
            1000.0,
            7,
            30,
            2
        );

        $this->assertFalse($result);
    }
}
