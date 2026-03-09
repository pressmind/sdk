<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use DateTime;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Touristic\EarlyBirdDiscountGroup\Item;
use Pressmind\ORM\Object\Touristic\Date;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Tests EarlyBird discount validity: time windows, booking_days_before_departure, room_condition_code_ibe.
 * Uses Reflection to call _checkEarlyBirdDiscount and _getEffectiveBookingDateFrom/To.
 */
class EarlyBirdValidityTest extends AbstractTestCase
{
    private function createDate(DateTime $departure): Date
    {
        $date = new Date(null, false);
        $date->departure = clone $departure;
        $date->arrival = (clone $departure)->modify('+7 days');
        return $date;
    }

    private function createDiscountItem(array $overrides = []): Item
    {
        $item = new Item(null, false);
        $item->type = 'P';
        $item->discount_value = 10.0;
        $item->booking_date_from = null;
        $item->booking_date_to = null;
        $item->travel_date_from = null;
        $item->travel_date_to = null;
        $item->booking_days_before_departure = 0;
        $item->room_condition_code_ibe = null;
        foreach ($overrides as $key => $value) {
            $item->$key = $value;
        }
        return $item;
    }

    private function invokeCheckEarlyBirdDiscount(Item $discount, Date $date, ?string $housingCodeIbe = null): bool
    {
        $mo = new MediaObject(null, false);
        $method = new \ReflectionMethod(MediaObject::class, '_checkEarlyBirdDiscount');
        $method->setAccessible(true);
        return $method->invoke($mo, $discount, $date, $housingCodeIbe);
    }

    private function invokeGetEffectiveBookingDateFrom(Item $discount, Date $date): ?DateTime
    {
        $mo = new MediaObject(null, false);
        $method = new \ReflectionMethod(MediaObject::class, '_getEffectiveBookingDateFrom');
        $method->setAccessible(true);
        return $method->invoke($mo, $discount, $date);
    }

    private function invokeGetEffectiveBookingDateTo(Item $discount, Date $date): ?DateTime
    {
        $mo = new MediaObject(null, false);
        $method = new \ReflectionMethod(MediaObject::class, '_getEffectiveBookingDateTo');
        $method->setAccessible(true);
        return $method->invoke($mo, $discount, $date);
    }

    public function testNullDiscountReturnsFalse(): void
    {
        $date = $this->createDate(new DateTime('+30 days'));
        $mo = new MediaObject(null, false);
        $method = new \ReflectionMethod(MediaObject::class, '_checkEarlyBirdDiscount');
        $method->setAccessible(true);
        $this->assertFalse($method->invoke($mo, null, $date));
    }

    public function testActiveWhenBookingWindowContainsTodayAndDepartureInTravelWindow(): void
    {
        $now = new DateTime();
        $now->setTime(0, 0, 0);
        $departure = (clone $now)->modify('+30 days');
        $date = $this->createDate($departure);
        $discount = $this->createDiscountItem([
            'booking_date_from' => (clone $now)->modify('-10 days'),
            'booking_date_to' => (clone $now)->modify('+10 days'),
            'travel_date_from' => (clone $now)->modify('+20 days'),
            'travel_date_to' => (clone $now)->modify('+60 days'),
        ]);
        $this->assertTrue($this->invokeCheckEarlyBirdDiscount($discount, $date));
    }

    public function testExpiredWhenBookingToBeforeToday(): void
    {
        $now = new DateTime();
        $now->setTime(0, 0, 0);
        $departure = (clone $now)->modify('+30 days');
        $date = $this->createDate($departure);
        $discount = $this->createDiscountItem([
            'booking_date_from' => (clone $now)->modify('-20 days'),
            'booking_date_to' => (clone $now)->modify('-1 day'),
            'travel_date_from' => (clone $now)->modify('+20 days'),
            'travel_date_to' => (clone $now)->modify('+60 days'),
        ]);
        $this->assertFalse($this->invokeCheckEarlyBirdDiscount($discount, $date));
    }

    public function testNotYetActiveWhenBookingFromAfterToday(): void
    {
        $now = new DateTime();
        $now->setTime(0, 0, 0);
        $departure = (clone $now)->modify('+30 days');
        $date = $this->createDate($departure);
        $discount = $this->createDiscountItem([
            'booking_date_from' => (clone $now)->modify('+5 days'),
            'booking_date_to' => (clone $now)->modify('+20 days'),
            'travel_date_from' => (clone $now)->modify('+20 days'),
            'travel_date_to' => (clone $now)->modify('+60 days'),
        ]);
        $this->assertFalse($this->invokeCheckEarlyBirdDiscount($discount, $date));
    }

    public function testDepartureOutsideTravelWindowReturnsFalse(): void
    {
        $now = new DateTime();
        $now->setTime(0, 0, 0);
        $departure = (clone $now)->modify('+90 days');
        $date = $this->createDate($departure);
        $discount = $this->createDiscountItem([
            'booking_date_from' => (clone $now)->modify('-10 days'),
            'booking_date_to' => (clone $now)->modify('+10 days'),
            'travel_date_from' => (clone $now)->modify('+20 days'),
            'travel_date_to' => (clone $now)->modify('+60 days'),
        ]);
        $this->assertFalse($this->invokeCheckEarlyBirdDiscount($discount, $date));
    }

    public function testNullBookingDatesTreatedAsOpen(): void
    {
        $now = new DateTime();
        $now->setTime(0, 0, 0);
        $departure = (clone $now)->modify('+30 days');
        $date = $this->createDate($departure);
        $discount = $this->createDiscountItem([
            'booking_date_from' => null,
            'booking_date_to' => null,
            'travel_date_from' => (clone $now)->modify('+20 days'),
            'travel_date_to' => (clone $now)->modify('+60 days'),
        ]);
        $this->assertTrue($this->invokeCheckEarlyBirdDiscount($discount, $date));
    }

    public function testEffectiveBookingDateFromWithDaysBeforeDeparture(): void
    {
        $departure = new DateTime('2026-08-01 00:00:00');
        $date = $this->createDate($departure);
        $discount = $this->createDiscountItem([
            'booking_date_from' => null,
            'booking_date_to' => null,
            'booking_days_before_departure' => 30,
        ]);
        $from = $this->invokeGetEffectiveBookingDateFrom($discount, $date);
        $this->assertInstanceOf(DateTime::class, $from);
        $this->assertSame('2026-07-02', $from->format('Y-m-d'));
    }

    public function testEffectiveBookingDateToWithDaysBeforeDeparture(): void
    {
        $departure = new DateTime('2026-08-01 00:00:00');
        $date = $this->createDate($departure);
        $discount = $this->createDiscountItem([
            'booking_date_from' => null,
            'booking_date_to' => null,
            'booking_days_before_departure' => 30,
        ]);
        $to = $this->invokeGetEffectiveBookingDateTo($discount, $date);
        $this->assertInstanceOf(DateTime::class, $to);
        $this->assertSame('2026-08-01', $to->format('Y-m-d'));
    }

    public function testRoomConditionCodeIbeMismatchReturnsFalse(): void
    {
        $now = new DateTime();
        $now->setTime(0, 0, 0);
        $departure = (clone $now)->modify('+30 days');
        $date = $this->createDate($departure);
        $discount = $this->createDiscountItem([
            'booking_date_from' => (clone $now)->modify('-10 days'),
            'booking_date_to' => (clone $now)->modify('+10 days'),
            'travel_date_from' => (clone $now)->modify('+20 days'),
            'travel_date_to' => (clone $now)->modify('+60 days'),
            'room_condition_code_ibe' => 'DZ-STD',
        ]);
        $this->assertFalse($this->invokeCheckEarlyBirdDiscount($discount, $date, 'DZ-SUP'));
        $this->assertTrue($this->invokeCheckEarlyBirdDiscount($discount, $date, 'DZ-STD'));
    }

    public function testRoomConditionCodeIbeEmptyMatchesAny(): void
    {
        $now = new DateTime();
        $now->setTime(0, 0, 0);
        $departure = (clone $now)->modify('+30 days');
        $date = $this->createDate($departure);
        $discount = $this->createDiscountItem([
            'booking_date_from' => (clone $now)->modify('-10 days'),
            'booking_date_to' => (clone $now)->modify('+10 days'),
            'travel_date_from' => (clone $now)->modify('+20 days'),
            'travel_date_to' => (clone $now)->modify('+60 days'),
            'room_condition_code_ibe' => null,
        ]);
        $this->assertTrue($this->invokeCheckEarlyBirdDiscount($discount, $date, 'ANY-CODE'));
    }
}
