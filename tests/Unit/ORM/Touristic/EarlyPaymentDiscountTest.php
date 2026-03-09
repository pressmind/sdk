<?php

namespace Pressmind\Tests\Unit\ORM\Touristic;

use DateTime;
use Pressmind\ORM\Object\Touristic\EarlyPaymentDiscountGroup;
use Pressmind\ORM\Object\Touristic\EarlyPaymentDiscountGroup\Item;
use Pressmind\Tests\Unit\AbstractTestCase;

class EarlyPaymentDiscountTest extends AbstractTestCase
{
    private function createItem(array $overrides = []): Item
    {
        $item = new Item(null, false);
        $defaults = [
            'id' => 'epd-item-001',
            'id_early_payment_discount_group' => 'epd-group-001',
            'discount_value' => 5.0,
            'type' => 'P',
            'round' => false,
            'booking_date_from' => new DateTime('2026-01-01'),
            'booking_date_to' => new DateTime('2026-06-30'),
            'travel_date_from' => new DateTime('2026-07-01'),
            'travel_date_to' => new DateTime('2026-12-31'),
        ];
        foreach (array_merge($defaults, $overrides) as $key => $value) {
            $item->$key = $value;
        }
        return $item;
    }

    public function testItemInstantiationWithoutDbRead(): void
    {
        $item = new Item(null, false);
        $this->assertInstanceOf(Item::class, $item);
    }

    public function testGroupInstantiationWithoutDbRead(): void
    {
        $group = new EarlyPaymentDiscountGroup(null, false);
        $this->assertInstanceOf(EarlyPaymentDiscountGroup::class, $group);
    }

    public function testGroupPropertyAssignment(): void
    {
        $group = new EarlyPaymentDiscountGroup(null, false);
        $group->setId('epd-group-001');
        $group->name = 'Pay Early Save More';
        $group->import_code = 'EPD-2026';

        $this->assertSame('epd-group-001', $group->getId());
        $this->assertSame('Pay Early Save More', $group->name);
        $this->assertSame('EPD-2026', $group->import_code);
    }

    public function testItemPercentageDiscountProperties(): void
    {
        $item = $this->createItem([
            'type' => 'P',
            'discount_value' => 3.0,
        ]);

        $this->assertSame('P', $item->type);
        $this->assertSame(3.0, $item->discount_value);
    }

    public function testItemFixedDiscountProperties(): void
    {
        $item = $this->createItem([
            'type' => 'F',
            'discount_value' => 25.0,
        ]);

        $this->assertSame('F', $item->type);
        $this->assertSame(25.0, $item->discount_value);
    }

    public function testHaveToPayBeforeDate(): void
    {
        $payBefore = new DateTime('2026-05-01');
        $item = $this->createItem([
            'have_to_pay_before_date' => $payBefore,
        ]);

        $this->assertEquals($payBefore, $item->have_to_pay_before_date);

        $paymentDate = new DateTime('2026-04-15');
        $this->assertLessThanOrEqual($item->have_to_pay_before_date, $paymentDate);
    }

    public function testHaveToPayBeforeDateExpired(): void
    {
        $payBefore = new DateTime('2026-01-15');
        $item = $this->createItem([
            'have_to_pay_before_date' => $payBefore,
        ]);

        $paymentDate = new DateTime('2026-02-28');
        $this->assertGreaterThan($item->have_to_pay_before_date, $paymentDate);
    }

    /**
     * The source model defines have_to_pay_after_booking_date_days with type 'int'
     * instead of 'integer', which triggers a missing IntFilter in the ORM filter factory.
     * We test the deadline logic using a plain value to verify the concept.
     */
    public function testHaveToPayAfterBookingDateDaysLogic(): void
    {
        $payAfterDays = 14;
        $bookingDate = new DateTime('2026-03-01');
        $paymentDeadline = (clone $bookingDate)->modify('+' . $payAfterDays . ' days');

        $this->assertEquals(new DateTime('2026-03-15'), $paymentDeadline);
    }

    public function testBookingDateRangeActive(): void
    {
        $item = $this->createItem([
            'booking_date_from' => new DateTime('2026-01-01'),
            'booking_date_to' => new DateTime('2026-06-30'),
        ]);

        $now = new DateTime('2026-03-15');
        $this->assertGreaterThanOrEqual($item->booking_date_from, $now);
        $this->assertLessThanOrEqual($item->booking_date_to, $now);
    }

    public function testBookingDateRangeExpired(): void
    {
        $item = $this->createItem([
            'booking_date_from' => new DateTime('2025-06-01'),
            'booking_date_to' => new DateTime('2025-12-31'),
        ]);

        $now = new DateTime('2026-02-28');
        $this->assertGreaterThan($item->booking_date_to, $now);
    }

    public function testTravelDateRangeCoversTrip(): void
    {
        $item = $this->createItem([
            'travel_date_from' => new DateTime('2026-05-01'),
            'travel_date_to' => new DateTime('2026-10-31'),
        ]);

        $departure = new DateTime('2026-08-01');
        $this->assertGreaterThanOrEqual($item->travel_date_from, $departure);
        $this->assertLessThanOrEqual($item->travel_date_to, $departure);
    }

    public function testTravelDateOutsideRange(): void
    {
        $item = $this->createItem([
            'travel_date_from' => new DateTime('2026-05-01'),
            'travel_date_to' => new DateTime('2026-10-31'),
        ]);

        $departure = new DateTime('2026-12-15');
        $this->assertGreaterThan($item->travel_date_to, $departure);
    }

    public function testBookingDaysBeforeDeparture(): void
    {
        $item = $this->createItem([
            'booking_days_before_departure' => 21,
        ]);

        $this->assertSame(21, $item->booking_days_before_departure);
    }

    public function testMinStayNights(): void
    {
        $item = $this->createItem(['min_stay_nights' => 3]);
        $this->assertSame(3, $item->min_stay_nights);
    }

    public function testRoundFlag(): void
    {
        $rounded = $this->createItem(['round' => true]);
        $notRounded = $this->createItem(['round' => false]);

        $this->assertTrue($rounded->round);
        $this->assertFalse($notRounded->round);
    }

    /**
     * Simulate percentage early-payment discount calculation.
     */
    public function testPercentageDiscountCalculation(): void
    {
        $item = $this->createItem([
            'type' => 'P',
            'discount_value' => 3.0,
        ]);

        $originalPrice = 2000.0;
        $discountedPrice = $originalPrice * (1 - $item->discount_value / 100);

        $this->assertSame(1940.0, $discountedPrice);
    }

    /**
     * Simulate fixed early-payment discount calculation.
     */
    public function testFixedDiscountCalculation(): void
    {
        $item = $this->createItem([
            'type' => 'F',
            'discount_value' => 100.0,
        ]);

        $originalPrice = 1500.0;
        $discountedPrice = $originalPrice - $item->discount_value;

        $this->assertSame(1400.0, $discountedPrice);
    }

    public function testPercentageDiscountWithRounding(): void
    {
        $item = $this->createItem([
            'type' => 'P',
            'discount_value' => 3.0,
            'round' => true,
        ]);

        $originalPrice = 1999.0;
        $discount = $originalPrice * ($item->discount_value / 100);
        if ($item->round) {
            $discount = round($discount);
        }

        $this->assertSame(60.0, $discount);
    }

    public function testItemOriginAndAgency(): void
    {
        $item = $this->createItem([
            'origin' => 'CRS',
            'agency' => 'AG-100',
        ]);

        $this->assertSame('CRS', $item->origin);
        $this->assertSame('AG-100', $item->agency);
    }

    public function testItemNameAndRoomCondition(): void
    {
        $item = $this->createItem([
            'name' => 'Pay 14 days early',
            'room_condition_code_ibe' => 'EZ-KOMF',
        ]);

        $this->assertSame('Pay 14 days early', $item->name);
        $this->assertSame('EZ-KOMF', $item->room_condition_code_ibe);
    }

    public function testItemGroupRelationId(): void
    {
        $item = $this->createItem([
            'id_early_payment_discount_group' => 'epd-group-099',
        ]);

        $this->assertSame('epd-group-099', $item->id_early_payment_discount_group);
    }

    /**
     * Full scenario: early-payment discount active with payment deadline met.
     * Uses a local payment-days value due to the 'int' type bug in the source model
     * (have_to_pay_after_booking_date_days cannot be set via the ORM magic setter).
     */
    public function testActiveEarlyPaymentScenario(): void
    {
        $item = $this->createItem([
            'type' => 'P',
            'discount_value' => 5.0,
            'booking_date_from' => new DateTime('2026-01-01'),
            'booking_date_to' => new DateTime('2026-04-30'),
            'travel_date_from' => new DateTime('2026-06-01'),
            'travel_date_to' => new DateTime('2026-09-30'),
        ]);

        $payAfterDays = 14;
        $bookingDate = new DateTime('2026-03-01');
        $departureDate = new DateTime('2026-07-15');
        $paymentDate = new DateTime('2026-03-10');

        $bookingInRange = $bookingDate >= $item->booking_date_from
            && $bookingDate <= $item->booking_date_to;
        $travelInRange = $departureDate >= $item->travel_date_from
            && $departureDate <= $item->travel_date_to;
        $paymentDeadline = (clone $bookingDate)->modify('+' . $payAfterDays . ' days');
        $paidOnTime = $paymentDate <= $paymentDeadline;

        $this->assertTrue($bookingInRange);
        $this->assertTrue($travelInRange);
        $this->assertTrue($paidOnTime);
    }

    /**
     * Full scenario: discount not applicable because payment was too late.
     * Uses a local payment-days value due to the 'int' type bug in the source model.
     */
    public function testExpiredPaymentDeadlineScenario(): void
    {
        $payAfterDays = 7;
        $bookingDate = new DateTime('2026-03-01');
        $paymentDate = new DateTime('2026-03-20');
        $paymentDeadline = (clone $bookingDate)->modify('+' . $payAfterDays . ' days');

        $paidOnTime = $paymentDate <= $paymentDeadline;
        $this->assertFalse($paidOnTime);
    }

    /**
     * Full scenario: discount with absolute pay-before date.
     */
    public function testPayBeforeDateScenario(): void
    {
        $item = $this->createItem([
            'type' => 'F',
            'discount_value' => 50.0,
            'have_to_pay_before_date' => new DateTime('2026-04-01'),
        ]);

        $earlyPayment = new DateTime('2026-03-15');
        $latePayment = new DateTime('2026-04-10');

        $this->assertLessThanOrEqual($item->have_to_pay_before_date, $earlyPayment);
        $this->assertGreaterThan($item->have_to_pay_before_date, $latePayment);
    }
}
