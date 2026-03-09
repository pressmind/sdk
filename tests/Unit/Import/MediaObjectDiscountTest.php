<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\Import\MediaObjectDiscount;
use Pressmind\Tests\Unit\AbstractTestCase;

class MediaObjectDiscountTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $import = new MediaObjectDiscount();
        $this->assertInstanceOf(MediaObjectDiscount::class, $import);
        $this->assertIsArray($import->getLog());
        $this->assertIsArray($import->getErrors());
    }

    public function testImportWithEmptyDataDoesNotThrow(): void
    {
        $import = new MediaObjectDiscount();
        $import->import([], 999, 'full');
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithNonArrayDataDoesNotThrow(): void
    {
        $import = new MediaObjectDiscount();
        $import->import(null, 999, 'full');
        $this->addToAssertionCount(1);
    }

    public function testImportWithValidDiscounts(): void
    {
        $data = [
            (object) [
                'id' => 1,
                'value' => 10,
                'description' => 'Desc',
                'type' => 'percent',
                'agency' => null,
                'travel_date_from' => '2024-01-01',
                'travel_date_to' => '2024-12-31',
                'booking_date_from' => null,
                'booking_date_to' => null,
            ],
        ];
        $import = new MediaObjectDiscount();
        $import->import($data, 999, 'full');
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportSkipsDiscountWithEmptyValue(): void
    {
        $data = [
            (object) ['id' => 1, 'value' => null, 'description' => '', 'type' => '', 'agency' => null],
        ];
        $import = new MediaObjectDiscount();
        $import->import($data, 999, 'full');
        $this->assertCount(0, $import->getErrors());
    }
}
