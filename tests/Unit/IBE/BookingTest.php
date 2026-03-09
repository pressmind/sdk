<?php

namespace Pressmind\Tests\Unit\IBE;

use DateTime;
use Exception;
use Pressmind\IBE\Booking;
use Pressmind\Tests\Unit\AbstractTestCase;

class BookingTest extends AbstractTestCase
{
    private function createBookingData(array $params = [], ?array $settings = null): array
    {
        $data = ['params' => $params];
        if ($settings !== null) {
            $data['settings'] = $settings;
        }
        return $data;
    }

    private function createFullParams(): array
    {
        return [
            'imo' => 123,
            'idbp' => 'bp_1',
            'idhp' => 'hp_1',
            'idd' => 'date_1',
            'iho' => ['opt_1' => 2, 'opt_2' => 1],
            'ids' => 'S',
            'ido' => 'opt_single',
            'idt1' => '10,20',
            'idt2' => '30,40',
            'tt' => 'bus',
            't' => 'booking',
        ];
    }

    /**
     * Lightweight stand-in for Touristic\Booking\Package with findObjectInArray support.
     */
    private function createMockPackageObject(array $props = []): object
    {
        return new class($props) {
            public $insurance_group = null;
            public $extras = [];
            public $tickets = [];
            public $sightseeings = [];
            public $housing_packages = [];
            public $dates = [];
            public $created;

            public function __construct(array $props)
            {
                foreach ($props as $k => $v) {
                    $this->$k = $v;
                }
            }

            public function findObjectInArray(string $property, string $key, $value)
            {
                if (isset($this->$property) && is_array($this->$property)) {
                    foreach ($this->$property as $obj) {
                        if (isset($obj->$key) && $obj->$key == $value) {
                            return $obj;
                        }
                    }
                }
                return null;
            }
        };
    }

    /**
     * Lightweight stand-in for Touristic\Housing\Package with findObjectInArray support.
     */
    private function createMockHousingPackageObject(array $props = []): object
    {
        return new class($props) {
            public $id;
            public $options = [];

            public function __construct(array $props)
            {
                foreach ($props as $k => $v) {
                    $this->$k = $v;
                }
            }

            public function findObjectInArray(string $property, string $key, $value)
            {
                if (isset($this->$property) && is_array($this->$property)) {
                    foreach ($this->$property as $obj) {
                        if (isset($obj->$key) && $obj->$key == $value) {
                            return $obj;
                        }
                    }
                }
                return null;
            }
        };
    }

    /**
     * Lightweight stand-in for Touristic\Date with getHousingOptions / getEarlybirds support.
     */
    private function createMockDateObject(array $props = []): object
    {
        return new class($props) {
            public $id;
            public $id_booking_package;
            public $departure;
            public $arrival;
            public $startingpoint = null;
            public $transports = [];
            public $early_bird_discount_group = null;
            public $season;
            public $housingOptionsResult = [];
            public $earlybirdsResult = [];

            public function __construct(array $props)
            {
                foreach ($props as $k => $v) {
                    $this->$k = $v;
                }
            }

            public function getHousingOptions($param = null)
            {
                return $this->housingOptionsResult;
            }

            public function getEarlybirds($agency = null)
            {
                return $this->earlybirdsResult;
            }
        };
    }

    // ---------------------------------------------------------------
    // __construct
    // ---------------------------------------------------------------

    public function testConstructorParsesAllParams(): void
    {
        $booking = new Booking($this->createBookingData($this->createFullParams()));

        $this->assertSame(123, $booking->id_media_object);
        $this->assertSame('bp_1', $booking->id_booking_package);
        $this->assertSame('hp_1', $booking->id_housing_package);
        $this->assertSame('date_1', $booking->id_date);
        $this->assertSame(['opt_1' => 2, 'opt_2' => 1], $booking->ids_housing_options);
        $this->assertSame('S', $booking->id_season);
        $this->assertSame('opt_single', $booking->id_option);
        $this->assertSame('bus', $booking->transport_type);
        $this->assertSame('booking', $booking->request_type);
    }

    public function testConstructorWithMissingParams(): void
    {
        $booking = new Booking($this->createBookingData([]));

        $this->assertNull($booking->id_media_object);
        $this->assertNull($booking->id_booking_package);
        $this->assertNull($booking->id_housing_package);
        $this->assertNull($booking->id_date);
        $this->assertNull($booking->ids_housing_options);
        $this->assertNull($booking->id_season);
        $this->assertNull($booking->id_option);
        $this->assertNull($booking->id_transport_way_1);
        $this->assertNull($booking->id_transport_way_2);
        $this->assertNull($booking->request_type);
        $this->assertNull($booking->settings);
    }

    public function testConstructorWithEmptyDataArray(): void
    {
        $booking = new Booking([]);

        $this->assertNull($booking->id_media_object);
        $this->assertNull($booking->settings);
    }

    public function testConstructorExplodesTransportIdsIntoArrays(): void
    {
        $booking = new Booking($this->createBookingData([
            'idt1' => '10,20,30',
            'idt2' => '40,50',
        ]));

        $this->assertIsArray($booking->id_transport_way_1);
        $this->assertSame(['10', '20', '30'], $booking->id_transport_way_1);
        $this->assertIsArray($booking->id_transport_way_2);
        $this->assertSame(['40', '50'], $booking->id_transport_way_2);
    }

    public function testConstructorExplodesSingleTransportId(): void
    {
        $booking = new Booking($this->createBookingData([
            'idt1' => '10',
        ]));

        $this->assertIsArray($booking->id_transport_way_1);
        $this->assertSame(['10'], $booking->id_transport_way_1);
        $this->assertNull($booking->id_transport_way_2);
    }

    public function testConstructorSetsSettings(): void
    {
        $settings = ['steps' => ['foo' => 'bar']];
        $booking = new Booking($this->createBookingData([], $settings));

        $this->assertSame($settings, $booking->settings);
    }

    public function testConstructorDatePropertyDefaultsToNull(): void
    {
        $booking = new Booking($this->createBookingData([]));

        $this->assertNull($booking->date);
    }

    // ---------------------------------------------------------------
    // getDate
    // ---------------------------------------------------------------

    public function testGetDateThrowsExceptionWhenNoDateIdAvailable(): void
    {
        $booking = new Booking($this->createBookingData([]));

        $this->expectException(Exception::class);
        $booking->getDate();
    }

    public function testGetDateUsesIdDateWhenPIdIsNull(): void
    {
        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'id_booking_package' => 'bp_1',
        ]);

        $mockPackage = $this->createMockPackageObject([
            'dates' => [$mockDate],
        ]);

        $booking = new Booking($this->createBookingData([
            'idd' => 'date_1',
            'idbp' => 'bp_1',
        ]));
        $booking->booking_package = $mockPackage;

        $result = $booking->getDate();
        $this->assertSame($mockDate, $result);
    }

    public function testGetDateWithExplicitPId(): void
    {
        $mockDate = $this->createMockDateObject([
            'id' => 'date_99',
            'id_booking_package' => 'bp_1',
        ]);

        $mockPackage = $this->createMockPackageObject([
            'dates' => [$mockDate],
        ]);

        $booking = new Booking($this->createBookingData([
            'idbp' => 'bp_1',
        ]));
        $booking->booking_package = $mockPackage;

        $result = $booking->getDate('date_99');
        $this->assertSame($mockDate, $result);
    }

    public function testGetDateCachesResult(): void
    {
        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'id_booking_package' => 'bp_1',
        ]);

        $booking = new Booking($this->createBookingData(['idd' => 'date_1']));
        $booking->date = $mockDate;

        $result1 = $booking->getDate();
        $result2 = $booking->getDate();
        $this->assertSame($result1, $result2);
    }

    public function testGetDateWithExplicitPIdIgnoresIdDate(): void
    {
        $dateA = $this->createMockDateObject(['id' => 'date_A']);
        $dateB = $this->createMockDateObject(['id' => 'date_B']);

        $mockPackage = $this->createMockPackageObject([
            'dates' => [$dateA, $dateB],
        ]);

        $booking = new Booking($this->createBookingData([
            'idd' => 'date_A',
            'idbp' => 'bp_1',
        ]));
        $booking->booking_package = $mockPackage;

        $result = $booking->getDate('date_B');
        $this->assertSame($dateB, $result);
    }

    // ---------------------------------------------------------------
    // getBookingPackage
    // ---------------------------------------------------------------

    public function testGetBookingPackageReturnsCachedInstance(): void
    {
        $mockPackage = $this->createMockPackageObject();

        $booking = new Booking($this->createBookingData(['idbp' => 'bp_1']));
        $booking->booking_package = $mockPackage;

        $result1 = $booking->getBookingPackage();
        $result2 = $booking->getBookingPackage();
        $this->assertSame($result1, $result2);
        $this->assertSame($mockPackage, $result1);
    }

    // ---------------------------------------------------------------
    // getInsurances
    // ---------------------------------------------------------------

    public function testGetInsurancesReturnsEmptyArrayWhenNoInsuranceGroup(): void
    {
        $mockPackage = $this->createMockPackageObject([
            'insurance_group' => null,
        ]);

        $booking = new Booking($this->createBookingData(['idbp' => 'bp_1']));
        $booking->booking_package = $mockPackage;

        $result = $booking->getInsurances();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetInsurancesReturnsInsurancesFromGroup(): void
    {
        $insurance = new \stdClass();
        $insurance->id = 'ins_1';
        $insurance->name = 'Reiserücktritt';
        $insurance->price_tables = [];

        $insuranceGroup = new \stdClass();
        $insuranceGroup->insurances = [$insurance];

        $mockPackage = $this->createMockPackageObject([
            'insurance_group' => $insuranceGroup,
        ]);

        $booking = new Booking($this->createBookingData(['idbp' => 'bp_1']));
        $booking->booking_package = $mockPackage;

        $result = $booking->getInsurances();
        $this->assertCount(1, $result);
        $this->assertSame('ins_1', $result[0]->id);
    }

    public function testGetInsurancesAppendsNoInsuranceOptionWhenEnabled(): void
    {
        $mockPackage = $this->createMockPackageObject([
            'insurance_group' => null,
        ]);

        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'departure' => new DateTime('2026-06-01'),
            'arrival' => new DateTime('2026-06-08'),
        ]);

        $settings = [
            'steps' => [
                'insurances' => [
                    'show_no_insurance_option' => ['value' => true],
                    'no_insurance_title' => ['value' => 'No insurance'],
                    'no_insurance_text' => ['value' => 'I decline insurance.'],
                ],
            ],
        ];

        $booking = new Booking($this->createBookingData(['idbp' => 'bp_1', 'idd' => 'date_1'], $settings));
        $booking->booking_package = $mockPackage;
        $booking->date = $mockDate;

        $result = $booking->getInsurances();
        $this->assertCount(1, $result);
        $this->assertEmpty($result[0]->id);
        $this->assertSame('No insurance', $result[0]->name);
        $this->assertSame('I decline insurance.', $result[0]->description);
        $this->assertIsArray($result[0]->price_tables);
        $this->assertEquals(0, $result[0]->price_tables[0]->price);
    }

    public function testGetInsurancesNoInsuranceOptionUsesDefaults(): void
    {
        $mockPackage = $this->createMockPackageObject([
            'insurance_group' => null,
        ]);

        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'departure' => new DateTime('2026-06-01'),
            'arrival' => new DateTime('2026-06-08'),
        ]);

        $settings = [
            'steps' => [
                'insurances' => [
                    'show_no_insurance_option' => ['value' => true],
                ],
            ],
        ];

        $booking = new Booking($this->createBookingData(['idbp' => 'bp_1', 'idd' => 'date_1'], $settings));
        $booking->booking_package = $mockPackage;
        $booking->date = $mockDate;

        $result = $booking->getInsurances();
        $this->assertCount(1, $result);
        $this->assertSame('Keine Versicherung gewünscht', $result[0]->name);
        $this->assertStringContainsString('Ich wünsche keine Versicherung', $result[0]->description);
    }

    // ---------------------------------------------------------------
    // getInsurancePriceTablePackages
    // ---------------------------------------------------------------

    public function testGetInsurancePriceTablePackagesReturnsEmptyWhenNoInsuranceGroup(): void
    {
        $mockPackage = $this->createMockPackageObject([
            'insurance_group' => null,
        ]);

        $booking = new Booking($this->createBookingData(['idbp' => 'bp_1']));
        $booking->booking_package = $mockPackage;

        $result = $booking->getInsurancePriceTablePackages();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ---------------------------------------------------------------
    // getHousingPackage
    // ---------------------------------------------------------------

    public function testGetHousingPackageUsesIdHousingPackageWhenPIdIsNull(): void
    {
        $hp = $this->createMockHousingPackageObject(['id' => 'hp_1', 'options' => []]);

        $mockPackage = $this->createMockPackageObject([
            'housing_packages' => [$hp],
        ]);

        $booking = new Booking($this->createBookingData(['idbp' => 'bp_1', 'idhp' => 'hp_1']));
        $booking->booking_package = $mockPackage;

        $result = $booking->getHousingPackage();
        $this->assertSame($hp, $result);
    }

    public function testGetHousingPackageWithExplicitPId(): void
    {
        $hp = $this->createMockHousingPackageObject(['id' => 'hp_42', 'options' => []]);

        $mockPackage = $this->createMockPackageObject([
            'housing_packages' => [$hp],
        ]);

        $booking = new Booking($this->createBookingData(['idbp' => 'bp_1']));
        $booking->booking_package = $mockPackage;

        $result = $booking->getHousingPackage('hp_42');
        $this->assertSame($hp, $result);
    }

    public function testGetHousingPackageReturnsNullWhenNotFound(): void
    {
        $mockPackage = $this->createMockPackageObject([
            'housing_packages' => [],
        ]);

        $booking = new Booking($this->createBookingData(['idbp' => 'bp_1', 'idhp' => 'hp_missing']));
        $booking->booking_package = $mockPackage;

        $result = $booking->getHousingPackage();
        $this->assertNull($result);
    }

    // ---------------------------------------------------------------
    // getAllHousingOptions
    // ---------------------------------------------------------------

    public function testGetAllHousingOptionsReturnsNullWhenNoHousingPackage(): void
    {
        $booking = new Booking($this->createBookingData(['idbp' => 'bp_1']));

        $this->assertNull($booking->getAllHousingOptions());
    }

    public function testGetAllHousingOptionsReturnsOptionsFromHousingPackage(): void
    {
        $option = new \stdClass();
        $option->id = 'opt_1';
        $option->name = 'Doppelzimmer';

        $hp = $this->createMockHousingPackageObject([
            'id' => 'hp_1',
            'options' => [$option],
        ]);

        $mockPackage = $this->createMockPackageObject([
            'housing_packages' => [$hp],
        ]);

        $booking = new Booking($this->createBookingData(['idbp' => 'bp_1', 'idhp' => 'hp_1']));
        $booking->booking_package = $mockPackage;

        $result = $booking->getAllHousingOptions();
        $this->assertCount(1, $result);
        $this->assertSame('opt_1', $result[0]->id);
    }

    // ---------------------------------------------------------------
    // getHousingOptions
    // ---------------------------------------------------------------

    public function testGetHousingOptionsWithIdOption(): void
    {
        $option = new \stdClass();
        $option->id = 'opt_single';
        $option->name = 'Einzelzimmer';

        $hp = $this->createMockHousingPackageObject([
            'id' => 'hp_1',
            'options' => [$option],
        ]);

        $mockPackage = $this->createMockPackageObject([
            'housing_packages' => [$hp],
        ]);

        $booking = new Booking($this->createBookingData([
            'idbp' => 'bp_1',
            'idhp' => 'hp_1',
            'ido' => 'opt_single',
        ]));
        $booking->booking_package = $mockPackage;

        $result = $booking->getHousingOptions();
        $this->assertCount(1, $result);
        $this->assertSame('opt_single', $result[0]->id);
    }

    public function testGetHousingOptionsWithIdsHousingOptions(): void
    {
        $option1 = new \stdClass();
        $option1->id = 'opt_1';

        $option2 = new \stdClass();
        $option2->id = 'opt_2';

        $hp = $this->createMockHousingPackageObject([
            'id' => 'hp_1',
            'options' => [$option1, $option2],
        ]);

        $mockPackage = $this->createMockPackageObject([
            'housing_packages' => [$hp],
        ]);

        $booking = new Booking($this->createBookingData([
            'idbp' => 'bp_1',
            'idhp' => 'hp_1',
            'iho' => ['opt_1' => 2, 'opt_2' => 1],
        ]));
        $booking->booking_package = $mockPackage;

        $result = $booking->getHousingOptions();
        $this->assertCount(3, $result);
    }

    public function testGetHousingOptionsMultipliesAmountCorrectly(): void
    {
        $option = new \stdClass();
        $option->id = 'opt_1';
        $option->name = 'Doppelzimmer';

        $hp = $this->createMockHousingPackageObject([
            'id' => 'hp_1',
            'options' => [$option],
        ]);

        $mockPackage = $this->createMockPackageObject([
            'housing_packages' => [$hp],
        ]);

        $booking = new Booking($this->createBookingData([
            'idbp' => 'bp_1',
            'idhp' => 'hp_1',
            'iho' => ['opt_1' => 3],
        ]));
        $booking->booking_package = $mockPackage;

        $result = $booking->getHousingOptions();
        $this->assertCount(3, $result);
        foreach ($result as $opt) {
            $this->assertSame('opt_1', $opt->id);
        }
    }

    public function testGetHousingOptionsFallsBackToAllHousingOptions(): void
    {
        $option = new \stdClass();
        $option->id = 'opt_1';

        $hp = $this->createMockHousingPackageObject([
            'id' => 'hp_1',
            'options' => [$option],
        ]);

        $mockPackage = $this->createMockPackageObject([
            'housing_packages' => [$hp],
        ]);

        $booking = new Booking($this->createBookingData([
            'idbp' => 'bp_1',
            'idhp' => 'hp_1',
        ]));
        $booking->booking_package = $mockPackage;

        $result = $booking->getHousingOptions();
        $this->assertCount(1, $result);
        $this->assertSame('opt_1', $result[0]->id);
    }

    // ---------------------------------------------------------------
    // getAvailableHousingOptionsForDate
    // ---------------------------------------------------------------

    public function testGetAvailableHousingOptionsForDateDelegatesToDate(): void
    {
        $opt = new \stdClass();
        $opt->id = 'opt_avail';

        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'housingOptionsResult' => [$opt],
        ]);

        $booking = new Booking($this->createBookingData([
            'idd' => 'date_1',
            'idhp' => 'hp_1',
        ]));
        $booking->date = $mockDate;

        $result = $booking->getAvailableHousingOptionsForDate();
        $this->assertCount(1, $result);
    }

    public function testGetAvailableHousingOptionsForDateCachesResult(): void
    {
        $options = [new \stdClass()];

        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'housingOptionsResult' => $options,
        ]);

        $booking = new Booking($this->createBookingData([
            'idd' => 'date_1',
            'idhp' => 'hp_1',
        ]));
        $booking->date = $mockDate;

        $result1 = $booking->getAvailableHousingOptionsForDate();
        $result2 = $booking->getAvailableHousingOptionsForDate();
        $this->assertSame($result1, $result2);
    }

    // ---------------------------------------------------------------
    // getStartingpoint
    // ---------------------------------------------------------------

    public function testGetStartingpointReturnsDateStartingpoint(): void
    {
        $startingpoint = new \stdClass();
        $startingpoint->id = 'sp_1';

        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'startingpoint' => $startingpoint,
        ]);

        $booking = new Booking($this->createBookingData(['idd' => 'date_1']));
        $booking->date = $mockDate;

        $this->assertSame($startingpoint, $booking->getStartingpoint());
    }

    public function testGetStartingpointReturnsNullWhenDateHasNone(): void
    {
        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'startingpoint' => null,
        ]);

        $booking = new Booking($this->createBookingData(['idd' => 'date_1']));
        $booking->date = $mockDate;

        $this->assertNull($booking->getStartingpoint());
    }

    // ---------------------------------------------------------------
    // getTransports
    // ---------------------------------------------------------------

    public function testGetTransportsFiltersByTransportType(): void
    {
        $transport1 = new \stdClass();
        $transport1->id = 't_1';
        $transport1->type = 'bus';

        $transport2 = new \stdClass();
        $transport2->id = 't_2';
        $transport2->type = 'flight';

        $transport3 = new \stdClass();
        $transport3->id = 't_3';
        $transport3->type = 'bus';

        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'transports' => [$transport1, $transport2, $transport3],
        ]);

        $booking = new Booking($this->createBookingData([
            'idd' => 'date_1',
            'tt' => 'bus',
        ]));
        $booking->date = $mockDate;

        $result = $booking->getTransports();
        $this->assertCount(2, $result);
        foreach ($result as $t) {
            $this->assertSame('bus', $t->type);
        }
    }

    public function testGetTransportsReturnsEmptyWhenNoMatchingType(): void
    {
        $transport = new \stdClass();
        $transport->id = 't_1';
        $transport->type = 'flight';

        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'transports' => [$transport],
        ]);

        $booking = new Booking($this->createBookingData([
            'idd' => 'date_1',
            'tt' => 'bus',
        ]));
        $booking->date = $mockDate;

        $result = $booking->getTransports();
        $this->assertEmpty($result);
    }

    public function testGetTransportsReturnsEmptyWhenNoTransports(): void
    {
        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'transports' => [],
        ]);

        $booking = new Booking($this->createBookingData([
            'idd' => 'date_1',
            'tt' => 'bus',
        ]));
        $booking->date = $mockDate;

        $result = $booking->getTransports();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetTransportsWithoutTransportWayIdsReturnsAllMatchingType(): void
    {
        $transport1 = new \stdClass();
        $transport1->id = 't_1';
        $transport1->type = 'bus';

        $transport2 = new \stdClass();
        $transport2->id = 't_2';
        $transport2->type = 'bus';

        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'transports' => [$transport1, $transport2],
        ]);

        $booking = new Booking($this->createBookingData([
            'idd' => 'date_1',
            'tt' => 'bus',
        ]));
        $booking->date = $mockDate;

        $result = $booking->getTransports();
        $this->assertCount(2, $result);
    }

    // ---------------------------------------------------------------
    // hasPickServices
    // ---------------------------------------------------------------

    public function testHasPickServicesReturnsTrueWithPickupService(): void
    {
        $opt1 = new \stdClass();
        $opt1->is_pickup_service = true;

        $startingpoint = new \stdClass();
        $startingpoint->options = [$opt1];

        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'startingpoint' => $startingpoint,
            'transports' => [],
        ]);

        $booking = new Booking($this->createBookingData(['idd' => 'date_1']));
        $booking->date = $mockDate;

        $this->assertTrue($booking->hasPickServices());
    }

    public function testHasPickServicesReturnsFalseWithoutPickupService(): void
    {
        $opt1 = new \stdClass();
        $opt1->is_pickup_service = false;

        $startingpoint = new \stdClass();
        $startingpoint->options = [$opt1];

        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'startingpoint' => $startingpoint,
            'transports' => [],
        ]);

        $booking = new Booking($this->createBookingData(['idd' => 'date_1']));
        $booking->date = $mockDate;

        $this->assertFalse($booking->hasPickServices());
    }

    public function testHasPickServicesReturnsFalseWhenNoStartingpoint(): void
    {
        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'startingpoint' => null,
            'transports' => [],
        ]);

        $booking = new Booking($this->createBookingData(['idd' => 'date_1']));
        $booking->date = $mockDate;

        $this->assertFalse($booking->hasPickServices());
    }

    public function testHasPickServicesWithMixedOptions(): void
    {
        $opt1 = new \stdClass();
        $opt1->is_pickup_service = false;

        $opt2 = new \stdClass();
        $opt2->is_pickup_service = true;

        $startingpoint = new \stdClass();
        $startingpoint->options = [$opt1, $opt2];

        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'startingpoint' => $startingpoint,
            'transports' => [],
        ]);

        $booking = new Booking($this->createBookingData(['idd' => 'date_1']));
        $booking->date = $mockDate;

        $this->assertTrue($booking->hasPickServices());
    }

    // ---------------------------------------------------------------
    // hasStartingPoints
    // ---------------------------------------------------------------

    public function testHasStartingPointsReturnsTrueWithNonPickupOption(): void
    {
        $opt1 = new \stdClass();
        $opt1->is_pickup_service = false;

        $startingpoint = new \stdClass();
        $startingpoint->options = [$opt1];

        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'startingpoint' => $startingpoint,
            'transports' => [],
        ]);

        $booking = new Booking($this->createBookingData(['idd' => 'date_1']));
        $booking->date = $mockDate;

        $this->assertTrue($booking->hasStartingPoints());
    }

    public function testHasStartingPointsReturnsTrueWithTransportHavingStartingPoint(): void
    {
        $opt1 = new \stdClass();
        $opt1->is_pickup_service = true;

        $startingpoint = new \stdClass();
        $startingpoint->options = [$opt1];

        $transport = new \stdClass();
        $transport->id = 't_1';
        $transport->type = 'bus';
        $transport->id_starting_point = 'sp_1';

        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'startingpoint' => $startingpoint,
            'transports' => [$transport],
        ]);

        $booking = new Booking($this->createBookingData([
            'idd' => 'date_1',
            'tt' => 'bus',
        ]));
        $booking->date = $mockDate;

        $this->assertTrue($booking->hasStartingPoints());
    }

    public function testHasStartingPointsReturnsFalseWhenOnlyPickupServices(): void
    {
        $opt1 = new \stdClass();
        $opt1->is_pickup_service = true;

        $startingpoint = new \stdClass();
        $startingpoint->options = [$opt1];

        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'startingpoint' => $startingpoint,
            'transports' => [],
        ]);

        $booking = new Booking($this->createBookingData(['idd' => 'date_1']));
        $booking->date = $mockDate;

        $this->assertFalse($booking->hasStartingPoints());
    }

    public function testHasStartingPointsReturnsFalseWhenNullStartingpointAndNoTransports(): void
    {
        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'startingpoint' => null,
            'transports' => [],
        ]);

        $booking = new Booking($this->createBookingData(['idd' => 'date_1']));
        $booking->date = $mockDate;

        $this->assertFalse($booking->hasStartingPoints());
    }

    // ---------------------------------------------------------------
    // getAllAvailableExtras
    // ---------------------------------------------------------------

    public function testGetAllAvailableExtrasFiltersBySeason(): void
    {
        $extra1 = new \stdClass();
        $extra1->season = 'S';
        $extra1->agencies = null;
        $extra1->reservation_date_from = null;
        $extra1->reservation_date_to = null;

        $extra2 = new \stdClass();
        $extra2->season = 'W';
        $extra2->agencies = null;
        $extra2->reservation_date_from = null;
        $extra2->reservation_date_to = null;

        $extra3 = new \stdClass();
        $extra3->season = null;
        $extra3->agencies = null;
        $extra3->reservation_date_from = null;
        $extra3->reservation_date_to = null;

        $mockPackage = $this->createMockPackageObject([
            'extras' => [$extra1, $extra2],
            'tickets' => [$extra3],
            'sightseeings' => [],
        ]);

        $booking = new Booking($this->createBookingData(['idbp' => 'bp_1']));
        $booking->booking_package = $mockPackage;

        $result = $booking->getAllAvailableExtras(null, null, 'S');
        $this->assertCount(2, $result);
    }

    public function testGetAllAvailableExtrasFiltersByAgency(): void
    {
        $extra1 = new \stdClass();
        $extra1->season = null;
        $extra1->agencies = 'ag_1,ag_2';
        $extra1->reservation_date_from = null;
        $extra1->reservation_date_to = null;

        $extra2 = new \stdClass();
        $extra2->season = null;
        $extra2->agencies = 'ag_3';
        $extra2->reservation_date_from = null;
        $extra2->reservation_date_to = null;

        $extra3 = new \stdClass();
        $extra3->season = null;
        $extra3->agencies = null;
        $extra3->reservation_date_from = null;
        $extra3->reservation_date_to = null;

        $mockPackage = $this->createMockPackageObject([
            'extras' => [$extra1, $extra2, $extra3],
            'tickets' => [],
            'sightseeings' => [],
        ]);

        $booking = new Booking($this->createBookingData(['idbp' => 'bp_1']));
        $booking->booking_package = $mockPackage;

        $result = $booking->getAllAvailableExtras(null, null, null, 'ag_1');
        $this->assertCount(2, $result);
    }

    public function testGetAllAvailableExtrasFiltersByReservationDate(): void
    {
        $dateFrom = new DateTime('2026-06-01');
        $dateTo = new DateTime('2026-06-15');

        $extra1 = new \stdClass();
        $extra1->season = null;
        $extra1->agencies = null;
        $extra1->reservation_date_from = new DateTime('2026-06-01');
        $extra1->reservation_date_to = new DateTime('2026-06-15');

        $extra2 = new \stdClass();
        $extra2->season = null;
        $extra2->agencies = null;
        $extra2->reservation_date_from = new DateTime('2026-07-01');
        $extra2->reservation_date_to = new DateTime('2026-07-15');

        $mockPackage = $this->createMockPackageObject([
            'extras' => [$extra1, $extra2],
            'tickets' => [],
            'sightseeings' => [],
        ]);

        $booking = new Booking($this->createBookingData(['idbp' => 'bp_1']));
        $booking->booking_package = $mockPackage;

        $result = $booking->getAllAvailableExtras($dateFrom, $dateTo);
        $this->assertCount(1, $result);
    }

    public function testGetAllAvailableExtrasDashSeasonTreatedAsNull(): void
    {
        $extra = new \stdClass();
        $extra->season = '-';
        $extra->agencies = null;
        $extra->reservation_date_from = null;
        $extra->reservation_date_to = null;

        $mockPackage = $this->createMockPackageObject([
            'extras' => [$extra],
            'tickets' => [],
            'sightseeings' => [],
        ]);

        $booking = new Booking($this->createBookingData(['idbp' => 'bp_1']));
        $booking->booking_package = $mockPackage;

        $result = $booking->getAllAvailableExtras(null, null, '-');
        $this->assertCount(1, $result);
    }

    public function testGetAllAvailableExtrasMergesExtrasTicketsAndSightseeings(): void
    {
        $extra = new \stdClass();
        $extra->season = null;
        $extra->agencies = null;
        $extra->reservation_date_from = null;
        $extra->reservation_date_to = null;

        $ticket = new \stdClass();
        $ticket->season = null;
        $ticket->agencies = null;
        $ticket->reservation_date_from = null;
        $ticket->reservation_date_to = null;

        $sightseeing = new \stdClass();
        $sightseeing->season = null;
        $sightseeing->agencies = null;
        $sightseeing->reservation_date_from = null;
        $sightseeing->reservation_date_to = null;

        $mockPackage = $this->createMockPackageObject([
            'extras' => [$extra],
            'tickets' => [$ticket],
            'sightseeings' => [$sightseeing],
        ]);

        $booking = new Booking($this->createBookingData(['idbp' => 'bp_1']));
        $booking->booking_package = $mockPackage;

        $result = $booking->getAllAvailableExtras();
        $this->assertCount(3, $result);
    }

    public function testGetAllAvailableExtrasReturnsEmptyWhenNoExtras(): void
    {
        $mockPackage = $this->createMockPackageObject([
            'extras' => [],
            'tickets' => [],
            'sightseeings' => [],
        ]);

        $booking = new Booking($this->createBookingData(['idbp' => 'bp_1']));
        $booking->booking_package = $mockPackage;

        $result = $booking->getAllAvailableExtras();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ---------------------------------------------------------------
    // calculateExtras
    // ---------------------------------------------------------------

    public function testCalculateExtrasDelegatesToCalculatePrice(): void
    {
        $extra1 = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['calculatePrice'])
            ->getMock();
        $extra1->expects($this->once())
            ->method('calculatePrice')
            ->with(7, 6);

        $extra2 = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['calculatePrice'])
            ->getMock();
        $extra2->expects($this->once())
            ->method('calculatePrice')
            ->with(7, 6);

        $booking = new Booking($this->createBookingData([]));
        $result = $booking->calculateExtras([$extra1, $extra2], 7, 6);
        $this->assertCount(2, $result);
    }

    public function testCalculateExtrasReturnsSameArray(): void
    {
        $extra = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['calculatePrice'])
            ->getMock();
        $extra->method('calculatePrice');

        $extras = [$extra];
        $booking = new Booking($this->createBookingData([]));
        $result = $booking->calculateExtras($extras, 5, 4);

        $this->assertSame($extras, $result);
    }

    public function testCalculateExtrasWithEmptyArray(): void
    {
        $booking = new Booking($this->createBookingData([]));
        $result = $booking->calculateExtras([], 7, 6);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ---------------------------------------------------------------
    // getEarlyBird
    // ---------------------------------------------------------------

    public function testGetEarlyBirdReturnsNullWhenNoDiscountGroup(): void
    {
        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'early_bird_discount_group' => null,
        ]);

        $booking = new Booking($this->createBookingData(['idd' => 'date_1']));
        $booking->date = $mockDate;

        $result = $booking->getEarlyBird();
        $this->assertNull($result);
    }

    public function testGetEarlyBirdReturnsNullWithEmptyDiscountGroup(): void
    {
        $mockDate = $this->createMockDateObject([
            'id' => 'date_1',
            'early_bird_discount_group' => false,
        ]);

        $booking = new Booking($this->createBookingData(['idd' => 'date_1']));
        $booking->date = $mockDate;

        $result = $booking->getEarlyBird();
        $this->assertNull($result);
    }
}
