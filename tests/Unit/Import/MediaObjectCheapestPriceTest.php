<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\Import\MediaObjectCheapestPrice;
use Pressmind\Tests\Unit\AbstractTestCase;

class MediaObjectCheapestPriceTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $import = new MediaObjectCheapestPrice();
        $this->assertInstanceOf(MediaObjectCheapestPrice::class, $import);
        $this->assertIsArray($import->getLog());
        $this->assertIsArray($import->getErrors());
    }

    public function testImportWithEmptyArrayDoesNotThrow(): void
    {
        $import = new MediaObjectCheapestPrice();
        $import->import([], 999, 'full');
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithNonArrayDoesNotThrow(): void
    {
        $import = new MediaObjectCheapestPrice();
        $import->import(null, 999, 'full');
        $this->addToAssertionCount(1);
    }

    public function testImportWithNoValidPricesLogsAndReturns(): void
    {
        $data = [
            (object) ['price' => null, 'duration' => 7],
            (object) ['price' => 0, 'duration' => 7],
        ];
        $import = new MediaObjectCheapestPrice();
        $import->import($data, 999, 'full');
        $this->assertNotEmpty($import->getLog());
    }

    public function testImportWithValidPrice(): void
    {
        $data = [
            (object) [
                'price' => 100,
                'price_pseudo' => 120,
                'duration' => 7,
                'occupancy_min' => 1,
                'occupancy_max' => 2,
                'occupancy' => 2,
                'description_1' => 'Option',
                'description_2' => null,
                'valid_from' => '2024-01-01 00:00:00',
                'valid_to' => '2024-12-31 00:00:00',
            ],
        ];
        $import = new MediaObjectCheapestPrice();
        $import->import($data, 999, 'full');
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithDefaultValidFromAndTo(): void
    {
        $data = [
            (object) [
                'price' => 50,
                'price_pseudo' => null,
                'duration' => 3,
                'occupancy_min' => 1,
                'occupancy_max' => 2,
                'occupancy' => 1,
                'description_1' => 'Test',
                'description_2' => null,
                'valid_from' => '1970-01-01 00:00:00',
                'valid_to' => '',
            ],
        ];
        $import = new MediaObjectCheapestPrice();
        $import->import($data, 888, 'full');
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithDurationZeroUsesOneDayInterval(): void
    {
        $data = [
            (object) [
                'price' => 10,
                'duration' => 0,
                'occupancy_min' => 1,
                'occupancy_max' => 1,
                'occupancy' => 1,
                'description_1' => 'Day',
                'description_2' => null,
                'valid_from' => '2024-06-01 00:00:00',
                'valid_to' => '2024-06-02 00:00:00',
            ],
        ];
        $import = new MediaObjectCheapestPrice();
        $import->import($data, 777, 'full');
        $this->assertCount(0, $import->getErrors());
    }
}
