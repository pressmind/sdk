<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\Import\Itinerary;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class ItineraryTest extends AbstractTestCase
{
    public function testConstructorStoresIdMediaObject(): void
    {
        $import = new Itinerary(12345);
        $this->assertInstanceOf(Itinerary::class, $import);
        $this->assertCount(0, $import->getLog());
        $this->assertCount(0, $import->getErrors());
    }

    public function testConstructorCastsIdToInteger(): void
    {
        $import = new Itinerary('999');
        $this->assertInstanceOf(Itinerary::class, $import);
    }

    public function testImportWithMockedClientEmptyResult(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        // _checkApiResponse accepts result when it is an array; empty array = no itinerary data
        $client->method('sendRequest')->willReturn((object) ['result' => [], 'error' => false]);
        Registry::getInstance()->add('rest_client', $client);
        $import = new Itinerary(999);
        $import->import();
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithMockedClientExceptionAddsError(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willThrowException(new \Exception('API failed'));
        Registry::getInstance()->add('rest_client', $client);
        $import = new Itinerary(999);
        $import->import();
        $this->assertCount(1, $import->getErrors());
    }

    public function testImportWithApiErrorAddsError(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willReturn((object) ['error' => true, 'msg' => 'Not found']);
        Registry::getInstance()->add('rest_client', $client);
        $import = new Itinerary(999);
        $import->import();
        $this->assertCount(1, $import->getErrors());
    }
}
