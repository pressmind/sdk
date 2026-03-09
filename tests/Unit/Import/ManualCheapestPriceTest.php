<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\Import\ManualCheapestPrice;
use Pressmind\Tests\Unit\AbstractTestCase;

class ManualCheapestPriceTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $data = [];
        $import = new ManualCheapestPrice($data);
        $this->assertInstanceOf(ManualCheapestPrice::class, $import);
        $this->assertIsArray($import->getLog());
        $this->assertIsArray($import->getErrors());
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithEmptyPriceSkipsItem(): void
    {
        $data = [
            (object) ['price' => null, 'description_1' => 'x'],
            (object) ['price' => '', 'description_1' => 'y'],
        ];
        $import = new ManualCheapestPrice($data);
        $import->import();
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithValidPrice(): void
    {
        $data = [
            (object) [
                'price' => 100.00,
                'price_pseudo' => 120.00,
                'description_1' => 'Option',
                'description_2' => null,
                'occupancy_min' => 1,
                'occupancy_max' => 2,
                'occupancy' => 2,
                'duration' => 7,
            ],
        ];
        $import = new ManualCheapestPrice($data);
        $import->import();
        $this->assertCount(0, $import->getErrors());
    }
}
