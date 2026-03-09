<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\Import\TouristicData;
use Pressmind\Tests\Unit\AbstractTestCase;

class TouristicDataTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $import = new TouristicData();
        $this->assertInstanceOf(TouristicData::class, $import);
        $this->assertIsArray($import->getLog());
        $this->assertIsArray($import->getErrors());
        $this->assertCount(0, $import->getErrors());
    }
}
