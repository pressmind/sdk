<?php

namespace Pressmind\Tests\Unit\ORM\Touristic;

use DateTime;
use Pressmind\ORM\Object\Touristic\Booking\Package;
use Pressmind\ORM\Object\Touristic\Date;
use Pressmind\ORM\Object\Touristic\Housing\Package as HousingPackage;
use Pressmind\Tests\Unit\AbstractTestCase;

class BookingPackageQueryTest extends AbstractTestCase
{
    private function createPackage(array $props = []): Package
    {
        $pkg = new Package();
        $defaults = [
            'id' => 'bp-1',
            'id_media_object' => 100,
            'name' => 'Test Package',
            'duration' => 7,
            'order' => 1,
            'price_mix' => 'date_housing',
            'ibe_type' => 2,
        ];
        foreach (array_merge($defaults, $props) as $k => $v) {
            $pkg->$k = $v;
        }
        return $pkg;
    }

    private function createHousingPackage(array $props = []): HousingPackage
    {
        $hp = new HousingPackage();
        $defaults = [
            'id' => 'hp-' . uniqid(),
            'id_media_object' => 100,
            'id_booking_package' => 'bp-1',
            'name' => 'Standard Room',
            'nights' => 7,
            'room_type' => 'room',
            'options' => [],
        ];
        foreach (array_merge($defaults, $props) as $k => $v) {
            $hp->$k = $v;
        }
        return $hp;
    }

    // --- hasValidDates() ---

    public function testHasValidDatesReturnsFalseWhenNoDates(): void
    {
        $pkg = $this->createPackage(['dates' => []]);
        $this->assertFalse($pkg->hasValidDates());
    }

    // --- validateHousingPackages() ---

    public function testValidateHousingPackagesReturnsErrorWhenEmpty(): void
    {
        $pkg = $this->createPackage(['housing_packages' => []]);
        $result = $pkg->validateHousingPackages();

        $this->assertNotEmpty($result);
        $hasNoHousingError = false;
        foreach ($result as $msg) {
            if (str_contains($msg, 'No housing packages')) {
                $hasNoHousingError = true;
            }
        }
        $this->assertTrue($hasNoHousingError, 'Expected error about missing housing packages');
    }

    public function testValidateHousingPackagesReturnsErrorForDuplicateNames(): void
    {
        $hp1 = $this->createHousingPackage(['id' => 'hp-1', 'name' => 'Deluxe']);
        $hp2 = $this->createHousingPackage(['id' => 'hp-2', 'name' => 'Deluxe']);

        $pkg = $this->createPackage(['housing_packages' => [$hp1, $hp2]]);
        $result = $pkg->validateHousingPackages();

        $hasDuplicateError = false;
        foreach ($result as $msg) {
            if (str_contains($msg, 'same name')) {
                $hasDuplicateError = true;
            }
        }
        $this->assertTrue($hasDuplicateError, 'Expected error about duplicate housing package names');
    }
}
