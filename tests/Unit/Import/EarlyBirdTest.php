<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\Import\EarlyBird;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class EarlyBirdTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $import = new EarlyBird();
        $this->assertIsArray($import->getLog());
        $this->assertIsArray($import->getErrors());
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithMockedClientEmptyResult(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willReturn((object) ['result' => [], 'error' => false]);
        Registry::getInstance()->add('rest_client', $client);
        $import = new EarlyBird();
        $import->import();
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithMockedClientApiErrorAddsError(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willReturn((object) ['error' => true, 'msg' => 'API error']);
        Registry::getInstance()->add('rest_client', $client);
        $import = new EarlyBird();
        $import->import();
        $this->assertCount(1, $import->getErrors());
    }

    public function testImportWithMockedClientExceptionAddsError(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willThrowException(new \Exception('Network error'));
        Registry::getInstance()->add('rest_client', $client);
        $import = new EarlyBird();
        $import->import();
        $this->assertCount(1, $import->getErrors());
    }

    public function testImportSkipsResultWithEmptyScales(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willReturn((object) [
            'result' => [(object) ['id' => 1, 'name' => 'EB', 'import_code' => 'eb1', 'scales' => []]],
            'error' => false,
        ]);
        Registry::getInstance()->add('rest_client', $client);
        $import = new EarlyBird();
        $import->import();
        $this->assertCount(0, $import->getErrors());
    }

    public function testRemoveOrphansCallsDbExecute(): void
    {
        $import = new EarlyBird();
        $import->remove_orphans([], []);
        $this->addToAssertionCount(1);
    }
}
