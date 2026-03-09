<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use DateTime;
use Pressmind\ORM\Object\CheapestPriceSpeed;
use Pressmind\Tests\Unit\AbstractTestCase;

class CheapestPriceSpeedTest extends AbstractTestCase
{
    private function createPriceObject(): CheapestPriceSpeed
    {
        return new CheapestPriceSpeed(null, false);
    }

    /**
     * Populate the minimal fields needed for an id_based fingerprint (booking_package_ibe_type < 2).
     */
    private function populateIdBasedFields(CheapestPriceSpeed $obj, array $overrides = []): void
    {
        $defaults = [
            'id' => 1,
            'id_media_object' => '100',
            'id_booking_package' => 'bp-200',
            'id_housing_package' => 'hp-300',
            'id_date' => 'dt-400',
            'id_option' => 'opt-500',
            'id_transport_1' => 'tr1-600',
            'id_transport_2' => 'tr2-700',
            'id_option_auto_book' => 0,
            'id_option_required_group' => 0,
            'id_startingpoint_option' => 'spo-800',
            'id_origin' => 1,
            'id_startingpoint' => 'sp-900',
            'id_included_options' => 'io-1000',
            'startingpoint_id_city' => 'city-1100',
            'housing_package_id_name' => 'hpn-1200',
            'agency' => 'AGY',
            'booking_package_ibe_type' => 1,
            // createFingerprint() eagerly builds both arrays, so DateTime fields are always accessed
            'date_departure' => new DateTime('2026-06-01'),
            'date_arrival' => new DateTime('2026-06-08'),
        ];

        foreach (array_merge($defaults, $overrides) as $key => $value) {
            $obj->$key = $value;
        }
    }

    /**
     * Populate the minimal fields needed for a code_based fingerprint (booking_package_ibe_type >= 2).
     */
    private function populateCodeBasedFields(CheapestPriceSpeed $obj, array $overrides = []): void
    {
        $defaults = [
            'id_media_object' => '100',
            'date_departure' => new DateTime('2026-06-01'),
            'date_arrival' => new DateTime('2026-06-08'),
            'date_code_ibe' => 'DC-001',
            'housing_package_code_ibe' => 'HPC-001',
            'option_code_ibe' => 'OC-001',
            'option_code_ibe_board_type' => 'AI',
            'option_code_ibe_board_type_category' => 'FULL',
            'option_code_ibe_category' => 'DZ',
            'transport_1_code_ibe' => 'TC1-001',
            'transport_2_code_ibe' => 'TC2-001',
            'startingpoint_code_ibe' => 'SPC-001',
            'code_ibe_included_options' => 'CIO-001',
            'agency' => 'AGY',
            'booking_package_ibe_type' => 2,
        ];

        foreach (array_merge($defaults, $overrides) as $key => $value) {
            $obj->$key = $value;
        }
    }

    public function testIdBasedFingerprintReturns64CharHexString(): void
    {
        $obj = $this->createPriceObject();
        $this->populateIdBasedFields($obj);

        $fingerprint = $obj->createFingerprint();

        $this->assertSame(64, strlen($fingerprint));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $fingerprint);
    }

    public function testCodeBasedFingerprintReturns64CharHexString(): void
    {
        $obj = $this->createPriceObject();
        $this->populateCodeBasedFields($obj);

        $fingerprint = $obj->createFingerprint();

        $this->assertSame(64, strlen($fingerprint));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $fingerprint);
    }

    public function testIdBasedFingerprintIsDeterministic(): void
    {
        $objA = $this->createPriceObject();
        $this->populateIdBasedFields($objA);

        $objB = $this->createPriceObject();
        $this->populateIdBasedFields($objB);

        $this->assertSame($objA->createFingerprint(), $objB->createFingerprint());
    }

    public function testCodeBasedFingerprintIsDeterministic(): void
    {
        $objA = $this->createPriceObject();
        $this->populateCodeBasedFields($objA);

        $objB = $this->createPriceObject();
        $this->populateCodeBasedFields($objB);

        $this->assertSame($objA->createFingerprint(), $objB->createFingerprint());
    }

    public function testDifferentIdBasedInputProducesDifferentFingerprint(): void
    {
        $objA = $this->createPriceObject();
        $this->populateIdBasedFields($objA);

        $objB = $this->createPriceObject();
        $this->populateIdBasedFields($objB, ['id_option' => 'opt-DIFFERENT']);

        $this->assertNotSame($objA->createFingerprint(), $objB->createFingerprint());
    }

    public function testDifferentCodeBasedInputProducesDifferentFingerprint(): void
    {
        $objA = $this->createPriceObject();
        $this->populateCodeBasedFields($objA);

        $objB = $this->createPriceObject();
        $this->populateCodeBasedFields($objB, ['option_code_ibe' => 'OC-DIFFERENT']);

        $this->assertNotSame($objA->createFingerprint(), $objB->createFingerprint());
    }

    public function testIbeTypeThresholdSelectsCorrectBranch(): void
    {
        $codeBased = $this->createPriceObject();
        $this->populateCodeBasedFields($codeBased, ['booking_package_ibe_type' => 2]);

        $idBased = $this->createPriceObject();
        $this->populateIdBasedFields($idBased, ['booking_package_ibe_type' => 1]);

        // Different branches must yield different fingerprints
        $this->assertNotSame($codeBased->createFingerprint(), $idBased->createFingerprint());
    }

    public function testIbeTypeAboveThresholdUsesCodeBased(): void
    {
        $objA = $this->createPriceObject();
        $this->populateCodeBasedFields($objA, ['booking_package_ibe_type' => 2]);

        $objB = $this->createPriceObject();
        $this->populateCodeBasedFields($objB, ['booking_package_ibe_type' => 5]);

        // Both >= 2: same code_based fields => same fingerprint
        $this->assertSame($objA->createFingerprint(), $objB->createFingerprint());
    }

    public function testDifferentDepartureDateChangesFingerprintInCodeBased(): void
    {
        $objA = $this->createPriceObject();
        $this->populateCodeBasedFields($objA);

        $objB = $this->createPriceObject();
        $this->populateCodeBasedFields($objB, ['date_departure' => new DateTime('2026-07-15')]);

        $this->assertNotSame($objA->createFingerprint(), $objB->createFingerprint());
    }

    public function testDifferentAgencyChangesFingerprintInBothModes(): void
    {
        $idA = $this->createPriceObject();
        $this->populateIdBasedFields($idA, ['agency' => 'AGY-A']);
        $idB = $this->createPriceObject();
        $this->populateIdBasedFields($idB, ['agency' => 'AGY-B']);
        $this->assertNotSame($idA->createFingerprint(), $idB->createFingerprint());

        $codeA = $this->createPriceObject();
        $this->populateCodeBasedFields($codeA, ['agency' => 'AGY-A']);
        $codeB = $this->createPriceObject();
        $this->populateCodeBasedFields($codeB, ['agency' => 'AGY-B']);
        $this->assertNotSame($codeA->createFingerprint(), $codeB->createFingerprint());
    }
}
