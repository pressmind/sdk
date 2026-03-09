<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use DateTime;
use Pressmind\ORM\Object\CheapestPriceSpeed;
use Pressmind\Tests\Unit\AbstractTestCase;

class CheapestPriceSpeedPricingTest extends AbstractTestCase
{
    private function createPriceSpeed(array $overrides = []): CheapestPriceSpeed
    {
        $price = new CheapestPriceSpeed(null, false);
        $defaults = [
            'id' => 1,
            'id_media_object' => 'mo-12345',
            'id_booking_package' => 'bp-001',
            'id_housing_package' => 'hp-001',
            'id_date' => 'dt-001',
            'id_option' => 'opt-001',
            'price_total' => 899.0,
            'price_regular_before_discount' => 899.0,
            'price_option' => 799.0,
            'price_transport_total' => 100.0,
            'duration' => 7.0,
            'date_departure' => new DateTime('2026-07-01'),
            'date_arrival' => new DateTime('2026-07-08'),
            'option_occupancy' => 2,
            'state' => 1,
        ];
        foreach (array_merge($defaults, $overrides) as $key => $value) {
            $price->$key = $value;
        }
        return $price;
    }

    public function testInstantiationWithoutDbRead(): void
    {
        $price = new CheapestPriceSpeed(null, false);
        $this->assertInstanceOf(CheapestPriceSpeed::class, $price);
    }

    public function testBasicPriceProperties(): void
    {
        $price = $this->createPriceSpeed();

        $this->assertSame(899.0, $price->price_total);
        $this->assertSame(799.0, $price->price_option);
        $this->assertSame(100.0, $price->price_transport_total);
    }

    public function testPriceWithoutEarlybirdDiscount(): void
    {
        $price = $this->createPriceSpeed([
            'price_total' => 1200.0,
            'price_regular_before_discount' => 1200.0,
            'earlybird_discount' => 0.0,
            'earlybird_discount_f' => 0.0,
            'earlybird_name' => null,
            'earlybird_discount_date_to' => null,
        ]);

        $this->assertSame(1200.0, $price->price_total);
        $this->assertSame(1200.0, $price->price_regular_before_discount);
        $this->assertSame(0.0, $price->earlybird_discount);
        $this->assertSame(0.0, $price->earlybird_discount_f);
        $this->assertSame($price->price_total, $price->price_regular_before_discount);
    }

    public function testPriceWithPercentageEarlybirdDiscount(): void
    {
        $price = $this->createPriceSpeed([
            'price_total' => 900.0,
            'price_regular_before_discount' => 1000.0,
            'earlybird_discount' => 10.0,
            'earlybird_discount_f' => 100.0,
            'earlybird_name' => 'Summer 10%',
            'earlybird_discount_date_to' => new DateTime('2026-05-31'),
        ]);

        $this->assertSame(900.0, $price->price_total);
        $this->assertSame(1000.0, $price->price_regular_before_discount);
        $this->assertSame(10.0, $price->earlybird_discount);
        $this->assertSame(100.0, $price->earlybird_discount_f);
        $this->assertSame('Summer 10%', $price->earlybird_name);
        $this->assertInstanceOf(DateTime::class, $price->earlybird_discount_date_to);
    }

    public function testPriceWithFixedEarlybirdDiscount(): void
    {
        $price = $this->createPriceSpeed([
            'price_total' => 850.0,
            'price_regular_before_discount' => 900.0,
            'earlybird_discount' => 0.0,
            'earlybird_discount_f' => 50.0,
            'earlybird_name' => 'Fixed 50 EUR off',
        ]);

        $this->assertSame(850.0, $price->price_total);
        $this->assertSame(900.0, $price->price_regular_before_discount);
        $this->assertSame(50.0, $price->earlybird_discount_f);
        $this->assertSame(50.0, $price->price_regular_before_discount - $price->price_total);
    }

    /**
     * Verify earlybird_discount (percentage) and earlybird_discount_f (fixed amount)
     * are consistent with the pre/post-discount prices.
     */
    public function testEarlybirdDiscountConsistency(): void
    {
        $regularPrice = 1000.0;
        $discountPercent = 15.0;
        $discountFixed = $regularPrice * ($discountPercent / 100);
        $totalPrice = $regularPrice - $discountFixed;

        $price = $this->createPriceSpeed([
            'price_total' => $totalPrice,
            'price_regular_before_discount' => $regularPrice,
            'earlybird_discount' => $discountPercent,
            'earlybird_discount_f' => $discountFixed,
        ]);

        $this->assertSame(850.0, $price->price_total);
        $this->assertEqualsWithDelta(
            $price->price_regular_before_discount - $price->price_total,
            $price->earlybird_discount_f,
            0.01
        );
    }

    public function testEarlybirdDiscountDateToFutureIsActive(): void
    {
        $price = $this->createPriceSpeed([
            'earlybird_discount' => 10.0,
            'earlybird_discount_f' => 90.0,
            'earlybird_discount_date_to' => new DateTime('2026-06-30'),
        ]);

        $now = new DateTime('2026-03-01');
        $this->assertLessThanOrEqual($price->earlybird_discount_date_to, $now);
    }

    public function testEarlybirdDiscountDateToPassedIsExpired(): void
    {
        $price = $this->createPriceSpeed([
            'earlybird_discount' => 10.0,
            'earlybird_discount_f' => 90.0,
            'earlybird_discount_date_to' => new DateTime('2025-12-31'),
        ]);

        $now = new DateTime('2026-02-28');
        $this->assertGreaterThan($price->earlybird_discount_date_to, $now);
    }

    public function testTransportPriceComponents(): void
    {
        $price = $this->createPriceSpeed([
            'price_transport_1' => 60.0,
            'price_transport_2' => 40.0,
            'price_transport_total' => 100.0,
        ]);

        $this->assertSame(60.0, $price->price_transport_1);
        $this->assertSame(40.0, $price->price_transport_2);
        $this->assertSame(100.0, $price->price_transport_total);
        $this->assertEqualsWithDelta(
            $price->price_transport_total,
            $price->price_transport_1 + $price->price_transport_2,
            0.01
        );
    }

    public function testPriceComposition(): void
    {
        $price = $this->createPriceSpeed([
            'price_option' => 700.0,
            'price_transport_total' => 150.0,
            'included_options_price' => 50.0,
            'price_total' => 900.0,
        ]);

        $this->assertSame(700.0, $price->price_option);
        $this->assertSame(150.0, $price->price_transport_total);
        $this->assertSame(50.0, $price->included_options_price);
        $this->assertSame(900.0, $price->price_total);
    }

    public function testPseudoPrice(): void
    {
        $price = $this->createPriceSpeed([
            'price_option' => 500.0,
            'price_option_pseudo' => 650.0,
        ]);

        $this->assertSame(500.0, $price->price_option);
        $this->assertSame(650.0, $price->price_option_pseudo);
        $this->assertGreaterThan($price->price_option, $price->price_option_pseudo);
    }

    public function testDurationAndDateRange(): void
    {
        $departure = new DateTime('2026-08-01');
        $arrival = new DateTime('2026-08-08');
        $price = $this->createPriceSpeed([
            'date_departure' => $departure,
            'date_arrival' => $arrival,
            'duration' => 7.0,
        ]);

        $this->assertEquals($departure, $price->date_departure);
        $this->assertEquals($arrival, $price->date_arrival);
        $this->assertSame(7.0, $price->duration);

        $daysDiff = $price->date_departure->diff($price->date_arrival)->days;
        $this->assertSame(7, $daysDiff);
    }

    public function testOccupancyProperties(): void
    {
        $price = $this->createPriceSpeed([
            'option_occupancy' => 2,
            'option_occupancy_min' => 1,
            'option_occupancy_max' => 3,
            'option_occupancy_child' => 1,
        ]);

        $this->assertSame(2, $price->option_occupancy);
        $this->assertSame(1, $price->option_occupancy_min);
        $this->assertSame(3, $price->option_occupancy_max);
        $this->assertSame(1, $price->option_occupancy_child);
        $this->assertGreaterThanOrEqual($price->option_occupancy_min, $price->option_occupancy);
        $this->assertLessThanOrEqual($price->option_occupancy_max, $price->option_occupancy);
    }

    public function testOptionPriceDueValues(): void
    {
        $validDueTypes = ['once', 'nightly', 'daily', 'weekly', 'stay', 'nights_person', 'person_stay', 'once_stay'];

        foreach ($validDueTypes as $dueType) {
            $price = $this->createPriceSpeed(['option_price_due' => $dueType]);
            $this->assertSame($dueType, $price->option_price_due);
        }
    }

    public function testBookingPackageProperties(): void
    {
        $price = $this->createPriceSpeed([
            'booking_package_name' => 'Standard Package',
            'booking_package_code' => 'STD-2026',
            'booking_package_type_of_travel' => 'PKW',
            'booking_package_ibe_type' => 2,
        ]);

        $this->assertSame('Standard Package', $price->booking_package_name);
        $this->assertSame('STD-2026', $price->booking_package_code);
        $this->assertSame('PKW', $price->booking_package_type_of_travel);
        $this->assertSame(2, $price->booking_package_ibe_type);
    }

    public function testStateProperty(): void
    {
        $active = $this->createPriceSpeed(['state' => 1]);
        $inactive = $this->createPriceSpeed(['state' => 0]);

        $this->assertSame(1, $active->state);
        $this->assertSame(0, $inactive->state);
    }

    public function testGuaranteedAndSavedFlags(): void
    {
        $price = $this->createPriceSpeed([
            'guaranteed' => true,
            'saved' => false,
        ]);

        $this->assertTrue($price->guaranteed);
        $this->assertFalse($price->saved);
    }

    public function testDiffToSingleRoom(): void
    {
        $price = $this->createPriceSpeed([
            'price_total' => 800.0,
            'diff_to_single_room' => 150.0,
            'option_occupancy' => 2,
        ]);

        $this->assertSame(150.0, $price->diff_to_single_room);
        $expectedSinglePrice = $price->price_total + $price->diff_to_single_room;
        $this->assertSame(950.0, $expectedSinglePrice);
    }

    /**
     * Verify that a discounted price is always lower than the regular price.
     */
    public function testDiscountedPriceLowerThanRegular(): void
    {
        $price = $this->createPriceSpeed([
            'price_total' => 750.0,
            'price_regular_before_discount' => 900.0,
            'earlybird_discount' => 16.67,
            'earlybird_discount_f' => 150.0,
        ]);

        $this->assertLessThan($price->price_regular_before_discount, $price->price_total);
    }

    /**
     * When no discount is applied, total equals regular price.
     */
    public function testNonDiscountedPriceEqualsRegular(): void
    {
        $price = $this->createPriceSpeed([
            'price_total' => 1100.0,
            'price_regular_before_discount' => 1100.0,
            'earlybird_discount' => 0.0,
            'earlybird_discount_f' => 0.0,
        ]);

        $this->assertSame($price->price_total, $price->price_regular_before_discount);
    }

    public function testVirtualCreatedPriceFlag(): void
    {
        $virtual = $this->createPriceSpeed(['is_virtual_created_price' => true]);
        $real = $this->createPriceSpeed(['is_virtual_created_price' => false]);

        $this->assertTrue($virtual->is_virtual_created_price);
        $this->assertFalse($real->is_virtual_created_price);
    }

    public function testQuotaPax(): void
    {
        $price = $this->createPriceSpeed(['quota_pax' => 20]);
        $this->assertSame(20, $price->quota_pax);
    }
}
