<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\Import\DataView;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class DataViewTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $import = new DataView();
        $this->assertIsArray($import->getLog());
        $this->assertIsArray($import->getErrors());
        $this->assertCount(0, $import->getLog());
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithMockedClientEmptyResult(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willReturn((object) ['result' => [], 'error' => false]);
        Registry::getInstance()->add('rest_client', $client);
        $import = new DataView();
        $import->import();
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithMockedClientWithDataViews(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willReturn((object) [
            'result' => [
                (object) [
                    'id' => 1,
                    'name' => 'View 1',
                    'active' => 1,
                    'filter' => [],
                ],
            ],
            'error' => false,
        ]);
        Registry::getInstance()->add('rest_client', $client);
        $import = new DataView();
        $import->import();
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithFilterNullLogsInfo(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willReturn((object) [
            'result' => [
                (object) [
                    'id' => 2,
                    'name' => 'View 2',
                    'active' => 1,
                    'filter' => null,
                ],
            ],
            'error' => false,
        ]);
        Registry::getInstance()->add('rest_client', $client);
        $import = new DataView();
        $import->import();
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportAddsErrorWhenApiReturnsError(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willReturn((object) ['error' => true, 'msg' => 'API error message']);
        Registry::getInstance()->add('rest_client', $client);
        $import = new DataView();
        $import->import();
        $this->assertCount(1, $import->getErrors());
        $this->assertStringContainsString('API error message', $import->getErrors()[0]);
    }

    public function testImportAddsErrorOnException(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willThrowException(new \Exception('Network failure'));
        Registry::getInstance()->add('rest_client', $client);
        $import = new DataView();
        $import->import();
        $this->assertCount(1, $import->getErrors());
    }
}
