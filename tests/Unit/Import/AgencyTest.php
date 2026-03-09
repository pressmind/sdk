<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\Import\Agency;
use Pressmind\Tests\Unit\AbstractTestCase;

class AgencyTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $agencies = [
            (object) ['id' => 'a1', 'name' => 'Agency One', 'code' => 'AG1'],
        ];
        $import = new Agency($agencies, 12345);
        $this->assertInstanceOf(Agency::class, $import);
        $this->assertIsArray($import->getLog());
        $this->assertIsArray($import->getErrors());
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithEmptyAgenciesReturnsEarly(): void
    {
        $import = new Agency([], 12345);
        $import->import();
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithAgenciesUsesCodeFromIdWhenCodeMissing(): void
    {
        $agencies = [(object) ['id' => 'ag99', 'name' => 'Agency 99']];
        $import = new Agency($agencies, 999);
        $import->import();
        $this->assertCount(0, $import->getErrors());
    }
}
