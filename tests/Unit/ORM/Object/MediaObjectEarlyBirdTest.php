<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use DateTime;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Touristic\Date;
use Pressmind\ORM\Object\Touristic\EarlyBirdDiscountGroup\Item;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for MediaObject Early Bird API: getEarlyBirdDiscount, checkEarlyBirdDiscountDateOnly.
 * Covers date logic for _getEffectiveBookingDateFrom/To, _checkEarlyBirdDiscount, and discount calculation (p/f).
 */
class MediaObjectEarlyBirdTest extends AbstractTestCase
{
    private function createMediaObject(?int $id = null): MediaObject
    {
        return new MediaObject($id, false);
    }

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
        $now = new DateTime('today');
        $item->booking_date_from = (clone $now)->modify('-10 days');
        $item->booking_date_to = (clone $now)->modify('+10 days');
        $item->travel_date_from = null;
        $item->travel_date_to = null;
        $item->booking_days_before_departure = null;
        $item->room_condition_code_ibe = null;
        $item->type = 'P';
        $item->discount_value = 5.0;
        foreach ($overrides as $key => $value) {
            $item->$key = $value;
        }
        return $item;
    }

    public function testGetEarlyBirdDiscountReturnsNullWhenDiscountsNull(): void
    {
        $mo = $this->createMediaObject();
        $date = $this->createDate(new DateTime('+30 days'));
        $this->assertNull($mo->getEarlyBirdDiscount([], $date));
    }

    public function testGetEarlyBirdDiscountReturnsMatchingDiscount(): void
    {
        $mo = $this->createMediaObject();
        $departure = new DateTime('+30 days');
        $date = $this->createDate($departure);
        $discount = $this->createDiscountItem([
            'travel_date_from' => (clone $departure)->modify('-1 day'),
            'travel_date_to' => (clone $departure)->modify('+1 day'),
        ]);
        $result = $mo->getEarlyBirdDiscount([$discount], $date);
        $this->assertSame($discount, $result);
    }

    public function testGetEarlyBirdDiscountReturnsNullWhenNoDiscountMatches(): void
    {
        $mo = $this->createMediaObject();
        $departure = new DateTime('+30 days');
        $date = $this->createDate($departure);
        $discount = $this->createDiscountItem([
            'travel_date_from' => new DateTime('2020-01-01'),
            'travel_date_to' => new DateTime('2020-12-31'),
        ]);
        $this->assertNull($mo->getEarlyBirdDiscount([$discount], $date));
    }

    public function testGetEarlyBirdDiscountReturnsNullWhenRoomConditionMismatch(): void
    {
        $mo = $this->createMediaObject();
        $departure = new DateTime('+30 days');
        $date = $this->createDate($departure);
        $discount = $this->createDiscountItem([
            'room_condition_code_ibe' => 'ROOM-A',
            'travel_date_from' => (clone $departure)->modify('-1 day'),
            'travel_date_to' => (clone $departure)->modify('+1 day'),
        ]);
        $result = $mo->getEarlyBirdDiscount([$discount], $date, 'ROOM-B');
        $this->assertNull($result);
    }

    public function testGetEarlyBirdDiscountReturnsDiscountWhenRoomConditionMatch(): void
    {
        $mo = $this->createMediaObject();
        $departure = new DateTime('+30 days');
        $date = $this->createDate($departure);
        $discount = $this->createDiscountItem([
            'room_condition_code_ibe' => 'ROOM-A',
            'travel_date_from' => (clone $departure)->modify('-1 day'),
            'travel_date_to' => (clone $departure)->modify('+1 day'),
        ]);
        $result = $mo->getEarlyBirdDiscount([$discount], $date, 'ROOM-A');
        $this->assertSame($discount, $result);
    }

    public function testCheckEarlyBirdDiscountDateOnlyReturnsFalseWhenDiscountNull(): void
    {
        $mo = $this->createMediaObject();
        $date = $this->createDate(new DateTime('+30 days'));
        $this->assertFalse($mo->checkEarlyBirdDiscountDateOnly(null, $date));
    }

    public function testCheckEarlyBirdDiscountDateOnlyReturnsTrueWhenDatesMatch(): void
    {
        $mo = $this->createMediaObject();
        $departure = new DateTime('+30 days');
        $date = $this->createDate($departure);
        $discount = $this->createDiscountItem([
            'travel_date_from' => (clone $departure)->modify('-1 day'),
            'travel_date_to' => (clone $departure)->modify('+1 day'),
        ]);
        $this->assertTrue($mo->checkEarlyBirdDiscountDateOnly($discount, $date));
    }

    public function testCheckEarlyBirdDiscountDateOnlyReturnsFalseWhenNowOutsideBookingWindow(): void
    {
        $mo = $this->createMediaObject();
        $departure = new DateTime('+30 days');
        $date = $this->createDate($departure);
        $discount = $this->createDiscountItem([
            'booking_date_from' => new DateTime('2020-01-01'),
            'booking_date_to' => new DateTime('2020-12-31'),
            'travel_date_from' => (clone $departure)->modify('-1 day'),
            'travel_date_to' => (clone $departure)->modify('+1 day'),
        ]);
        $this->assertFalse($mo->checkEarlyBirdDiscountDateOnly($discount, $date));
    }

    /**
     * Effective booking date from: when booking_date_from/to are null and booking_days_before_departure is set.
     */
    public function testCheckEarlyBirdDiscountDateOnlyWithBookingDaysBeforeDeparture(): void
    {
        $mo = $this->createMediaObject();
        $departure = new DateTime('+60 days');
        $departure->setTime(0, 0, 0);
        $date = $this->createDate($departure);
        $discount = $this->createDiscountItem([
            'booking_date_from' => null,
            'booking_date_to' => null,
            'booking_days_before_departure' => 90,
            'travel_date_from' => (clone $departure)->modify('-1 day'),
            'travel_date_to' => (clone $departure)->modify('+1 day'),
        ]);
        $this->assertTrue($mo->checkEarlyBirdDiscountDateOnly($discount, $date));
    }
}
