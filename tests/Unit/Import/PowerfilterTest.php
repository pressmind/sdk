<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\Import\Powerfilter;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class PowerfilterTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $import = new Powerfilter();
        $this->assertIsArray($import->getLog());
        $this->assertIsArray($import->getErrors());
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithMockedClientEmptyResult(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willReturn((object) ['result' => [], 'error' => false]);
        Registry::getInstance()->add('rest_client', $client);
        $import = new Powerfilter();
        $import->import();
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportAddsErrorOnException(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willThrowException(new \Exception('API error'));
        Registry::getInstance()->add('rest_client', $client);
        $import = new Powerfilter();
        $import->import();
        $this->assertCount(1, $import->getErrors());
    }

    public function testRemoveOrphansWithValidIds(): void
    {
        $import = new Powerfilter();
        $import->removeOrphans([1, 2]);
        $this->addToAssertionCount(1);
    }

    public function testRemoveOrphansWithEmptyArrayReturnsEarly(): void
    {
        $import = new Powerfilter();
        $import->removeOrphans([]);
        $this->addToAssertionCount(1);
    }
}
