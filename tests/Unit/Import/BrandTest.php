<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\Import\Brand;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class BrandTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $import = new Brand();
        $this->assertIsArray($import->getLog());
        $this->assertIsArray($import->getErrors());
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithMockedClientEmptyResult(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willReturn((object) ['result' => [], 'error' => false]);
        Registry::getInstance()->add('rest_client', $client);
        $import = new Brand();
        $import->import();
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithMockedClientWithBrands(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willReturn((object) [
            'result' => [
                (object) ['id' => 1, 'name' => 'Brand A', 'tags' => null, 'description' => 'Desc A'],
            ],
            'error' => false,
        ]);
        Registry::getInstance()->add('rest_client', $client);
        $import = new Brand();
        $import->import();
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportAddsErrorOnException(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willThrowException(new \Exception('API failed'));
        Registry::getInstance()->add('rest_client', $client);
        $import = new Brand();
        $import->import();
        $this->assertCount(1, $import->getErrors());
        $this->assertStringContainsString('API failed', $import->getErrors()[0]);
    }
}
