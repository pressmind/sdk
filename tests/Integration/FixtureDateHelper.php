<?php

namespace Pressmind\Tests\Integration;

use DateTime;

/**
 * Trait for date-relative fixtures in Integration and E2E tests.
 * Ensures travel dates (departure/arrival) and discount validity windows
 * are built relative to a reference date so tests do not break when run on different days.
 */
trait FixtureDateHelper
{
    /**
     * Reference date for all fixture dates (e.g. "today" or a fixed date).
     * Set in setUp(), e.g. $this->fixtureDateBase = new DateTime('today');
     *
     * @var DateTime|null
     */
    protected $fixtureDateBase;

    /**
     * Get the fixture reference date. Defaults to today at midnight if not set.
     */
    protected function getFixtureDateBase(): DateTime
    {
        if ($this->fixtureDateBase === null) {
            $this->fixtureDateBase = new DateTime('today');
        }
        return clone $this->fixtureDateBase;
    }

    /**
     * Departure date in the future relative to the reference date.
     * Use for travel dates so getCheapestPrices (date_departure > now), insertCheapestPrice and Calendar include them.
     *
     * @param int $daysFromToday Number of days from the reference date (e.g. 30 = 30 days in future)
     * @return DateTime
     */
    protected function departureInFuture(int $daysFromToday): DateTime
    {
        $base = $this->getFixtureDateBase();
        $base->modify($daysFromToday >= 0 ? "+{$daysFromToday} days" : "{$daysFromToday} days");
        return $base;
    }

    /**
     * Booking window so that "today" (reference date) lies inside the window.
     * Use for Early Bird / discount validity: booking_date_from .. booking_date_to.
     *
     * @param int $daysBefore Number of days before reference date for window start
     * @param int $daysAfter Number of days after reference date for window end
     * @return array{booking_date_from: DateTime, booking_date_to: DateTime}
     */
    protected function bookingWindowAroundToday(int $daysBefore, int $daysAfter): array
    {
        $base = $this->getFixtureDateBase();
        $from = clone $base;
        $from->modify("-{$daysBefore} days");
        $to = clone $base;
        $to->modify("+{$daysAfter} days");
        return [
            'booking_date_from' => $from,
            'booking_date_to' => $to,
        ];
    }

    /**
     * Travel date window for a given departure (e.g. same day for exact match).
     * Use for Early Bird travel_date_from / travel_date_to so the discount applies to that departure.
     *
     * @param DateTime $departure Departure date
     * @param int $daysFromStart Days before departure for travel_date_from (0 = same day)
     * @param int $daysToEnd Days after departure for travel_date_to (0 = same day)
     * @return array{travel_date_from: DateTime, travel_date_to: DateTime}
     */
    protected function travelWindowForDeparture(DateTime $departure, int $daysFromStart = 0, int $daysToEnd = 0): array
    {
        $from = clone $departure;
        $from->setTime(0, 0, 0);
        $from->modify($daysFromStart >= 0 ? "-{$daysFromStart} days" : "+" . abs($daysFromStart) . " days");
        $to = clone $departure;
        $to->setTime(23, 59, 59);
        $to->modify($daysToEnd >= 0 ? "+{$daysToEnd} days" : "-" . abs($daysToEnd) . " days");
        return [
            'travel_date_from' => $from,
            'travel_date_to' => $to,
        ];
    }
}
