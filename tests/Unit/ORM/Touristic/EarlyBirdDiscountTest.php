<?php

namespace Pressmind\Tests\Unit\ORM\Touristic;

use DateTime;
use Pressmind\ORM\Object\Touristic\EarlyBirdDiscountGroup;
use Pressmind\ORM\Object\Touristic\EarlyBirdDiscountGroup\Item;
use Pressmind\Tests\Unit\AbstractTestCase;

class EarlyBirdDiscountTest extends AbstractTestCase
{
    private function createItem(array $overrides = []): Item
    {
        $item = new Item(null, false);
        $defaults = [
            'id' => 'ebd-item-001',
            'id_early_bird_discount_group' => 'ebd-group-001',
            'discount_value' => 10.0,
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
        $group = new EarlyBirdDiscountGroup(null, false);
        $this->assertInstanceOf(EarlyBirdDiscountGroup::class, $group);
    }

    public function testGroupPropertyAssignment(): void
    {
        $group = new EarlyBirdDiscountGroup(null, false);
        $group->setId('ebd-group-001');
        $group->name = 'Summer Earlybird';
        $group->import_code = 'SUMMER-EB-2026';

        $this->assertSame('ebd-group-001', $group->getId());
        $this->assertSame('Summer Earlybird', $group->name);
        $this->assertSame('SUMMER-EB-2026', $group->import_code);
    }

    public function testItemPercentageDiscountProperties(): void
    {
        $item = $this->createItem([
            'type' => 'P',
            'discount_value' => 15.0,
        ]);

        $this->assertSame('P', $item->type);
        $this->assertSame(15.0, $item->discount_value);
    }

    public function testItemFixedDiscountProperties(): void
    {
        $item = $this->createItem([
            'type' => 'F',
            'discount_value' => 50.0,
        ]);

        $this->assertSame('F', $item->type);
        $this->assertSame(50.0, $item->discount_value);
    }

    public function testItemBookingDateRangeWithinActiveWindow(): void
    {
        $bookingFrom = new DateTime('2026-02-01');
        $bookingTo = new DateTime('2026-05-31');
        $item = $this->createItem([
            'booking_date_from' => $bookingFrom,
            'booking_date_to' => $bookingTo,
        ]);

        $now = new DateTime('2026-03-15');
        $this->assertGreaterThanOrEqual($item->booking_date_from, $now);
        $this->assertLessThanOrEqual($item->booking_date_to, $now);
    }

    public function testItemBookingDateRangeExpired(): void
    {
        $bookingFrom = new DateTime('2025-01-01');
        $bookingTo = new DateTime('2025-06-30');
        $item = $this->createItem([
            'booking_date_from' => $bookingFrom,
            'booking_date_to' => $bookingTo,
        ]);

        $now = new DateTime('2026-02-28');
        $this->assertGreaterThan($item->booking_date_to, $now);
    }

    public function testItemTravelDateRangeCoversTrip(): void
    {
        $item = $this->createItem([
            'travel_date_from' => new DateTime('2026-06-01'),
            'travel_date_to' => new DateTime('2026-09-30'),
        ]);

        $departure = new DateTime('2026-07-15');
        $this->assertGreaterThanOrEqual($item->travel_date_from, $departure);
        $this->assertLessThanOrEqual($item->travel_date_to, $departure);
    }

    public function testItemTravelDateOutsideRange(): void
    {
        $item = $this->createItem([
            'travel_date_from' => new DateTime('2026-06-01'),
            'travel_date_to' => new DateTime('2026-09-30'),
        ]);

        $departure = new DateTime('2026-11-01');
        $this->assertGreaterThan($item->travel_date_to, $departure);
    }

    public function testItemBookingDaysBeforeDeparture(): void
    {
        $item = $this->createItem([
            'booking_days_before_departure' => 30,
        ]);

        $this->assertSame(30, $item->booking_days_before_departure);

        $departure = new DateTime('2026-08-01');
        $latestBookingDate = (clone $departure)->modify('-' . $item->booking_days_before_departure . ' days');
        $bookingDate = new DateTime('2026-06-15');

        $this->assertLessThanOrEqual($latestBookingDate, $bookingDate);
    }

    public function testItemBookingDaysBeforeDepartureTooLate(): void
    {
        $item = $this->createItem([
            'booking_days_before_departure' => 60,
        ]);

        $departure = new DateTime('2026-08-01');
        $latestBookingDate = (clone $departure)->modify('-' . $item->booking_days_before_departure . ' days');
        $bookingDate = new DateTime('2026-07-20');

        $this->assertGreaterThan($latestBookingDate, $bookingDate);
    }

    public function testItemMinStayNights(): void
    {
        $item = $this->createItem([
            'min_stay_nights' => 7,
        ]);

        $this->assertSame(7, $item->min_stay_nights);
    }

    public function testItemRoundFlag(): void
    {
        $itemRounded = $this->createItem(['round' => true]);
        $itemNotRounded = $this->createItem(['round' => false]);

        $this->assertTrue($itemRounded->round);
        $this->assertFalse($itemNotRounded->round);
    }

    /**
     * Simulate percentage discount calculation: price * (1 - discount/100)
     */
    public function testPercentageDiscountCalculation(): void
    {
        $item = $this->createItem([
            'type' => 'P',
            'discount_value' => 10.0,
        ]);

        $originalPrice = 1000.0;
        $discountedPrice = $originalPrice * (1 - $item->discount_value / 100);

        $this->assertSame(900.0, $discountedPrice);
    }

    /**
     * Simulate fixed discount calculation: price - discount_value
     */
    public function testFixedDiscountCalculation(): void
    {
        $item = $this->createItem([
            'type' => 'F',
            'discount_value' => 75.0,
        ]);

        $originalPrice = 500.0;
        $discountedPrice = $originalPrice - $item->discount_value;

        $this->assertSame(425.0, $discountedPrice);
    }

    /**
     * Verify percentage discount with rounding enabled produces whole-number result.
     */
    public function testPercentageDiscountWithRounding(): void
    {
        $item = $this->createItem([
            'type' => 'P',
            'discount_value' => 7.0,
            'round' => true,
        ]);

        $originalPrice = 999.0;
        $discount = $originalPrice * ($item->discount_value / 100);
        if ($item->round) {
            $discount = round($discount);
        }

        $this->assertSame(70.0, $discount);
        $this->assertSame(929.0, $originalPrice - $discount);
    }

    public function testItemOriginAndAgency(): void
    {
        $item = $this->createItem([
            'origin' => 'API',
            'agency' => 'AG-001',
        ]);

        $this->assertSame('API', $item->origin);
        $this->assertSame('AG-001', $item->agency);
    }

    public function testItemNameAndRoomCondition(): void
    {
        $item = $this->createItem([
            'name' => 'Winter Earlybird 10%',
            'room_condition_code_ibe' => 'DZ-STD',
        ]);

        $this->assertSame('Winter Earlybird 10%', $item->name);
        $this->assertSame('DZ-STD', $item->room_condition_code_ibe);
    }

    public function testItemGroupRelationId(): void
    {
        $item = $this->createItem([
            'id_early_bird_discount_group' => 'ebd-group-042',
        ]);

        $this->assertSame('ebd-group-042', $item->id_early_bird_discount_group);
    }

    /**
     * Full scenario: active earlybird with all conditions satisfied.
     */
    public function testActiveEarlybirdScenario(): void
    {
        $item = $this->createItem([
            'type' => 'P',
            'discount_value' => 12.5,
            'booking_date_from' => new DateTime('2026-01-01'),
            'booking_date_to' => new DateTime('2026-04-30'),
            'travel_date_from' => new DateTime('2026-06-01'),
            'travel_date_to' => new DateTime('2026-09-30'),
            'booking_days_before_departure' => 45,
            'min_stay_nights' => 5,
        ]);

        $bookingDate = new DateTime('2026-03-01');
        $departureDate = new DateTime('2026-07-15');
        $stayNights = 7;

        $bookingInRange = $bookingDate >= $item->booking_date_from
            && $bookingDate <= $item->booking_date_to;
        $travelInRange = $departureDate >= $item->travel_date_from
            && $departureDate <= $item->travel_date_to;
        $earlyEnough = $bookingDate <= (clone $departureDate)->modify('-' . $item->booking_days_before_departure . ' days');
        $longEnoughStay = $stayNights >= $item->min_stay_nights;

        $this->assertTrue($bookingInRange);
        $this->assertTrue($travelInRange);
        $this->assertTrue($earlyEnough);
        $this->assertTrue($longEnoughStay);
    }

    /**
     * Full scenario: expired earlybird where booking window has passed.
     */
    public function testExpiredEarlybirdScenario(): void
    {
        $item = $this->createItem([
            'type' => 'P',
            'discount_value' => 10.0,
            'booking_date_from' => new DateTime('2025-06-01'),
            'booking_date_to' => new DateTime('2025-12-31'),
            'travel_date_from' => new DateTime('2026-03-01'),
            'travel_date_to' => new DateTime('2026-06-30'),
        ]);

        $bookingDate = new DateTime('2026-02-15');
        $bookingInRange = $bookingDate >= $item->booking_date_from
            && $bookingDate <= $item->booking_date_to;

        $this->assertFalse($bookingInRange);
    }
}
