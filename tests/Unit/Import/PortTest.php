<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\Import\Port;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class PortTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $import = new Port();
        $this->assertIsArray($import->getLog());
        $this->assertIsArray($import->getErrors());
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithMockedClientEmptyResult(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willReturn((object) ['result' => [], 'error' => false]);
        Registry::getInstance()->add('rest_client', $client);
        $import = new Port();
        $import->import();
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithMockedClientWithPorts(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willReturn((object) [
            'result' => [
                (object) ['id' => 1, 'code' => 'HAM', 'active' => 1, 'name' => 'Hamburg', 'description' => 'Port'],
            ],
            'error' => false,
        ]);
        Registry::getInstance()->add('rest_client', $client);
        $import = new Port();
        $import->import();
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportAddsErrorOnException(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willThrowException(new \Exception('Network error'));
        Registry::getInstance()->add('rest_client', $client);
        $import = new Port();
        $import->import();
        $this->assertCount(1, $import->getErrors());
    }
}
