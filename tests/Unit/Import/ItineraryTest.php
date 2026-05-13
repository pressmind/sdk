<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\DB\Adapter\AdapterInterface;
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

    public function testImportPersistsBoardDistanceAndSectionTags(): void
    {
        $inserted = [];
        $db = $this->createMock(AdapterInterface::class);
        $db->method('fetchAll')->willReturn([]);
        $db->method('fetchRow')->willReturn(null);
        $db->method('fetchOne')->willReturn(null);
        $db->method('getAffectedRows')->willReturn(0);
        $db->method('getTablePrefix')->willReturn('');
        $db->method('inTransaction')->willReturn(false);
        $db->method('execute')->willReturn(null);
        $db->method('insert')->willReturnCallback(function (string $tableName, array $data) use (&$inserted) {
            $inserted[$tableName][] = $data;
            return $data['id'] ?? 1;
        });
        $db->method('delete')->willReturn(null);
        $db->method('update')->willReturn(null);
        $db->method('replace')->willReturn(null);
        $db->method('truncate')->willReturn(null);
        $db->method('batchInsert')->willReturn(1);
        $db->method('beginTransaction')->willReturn(null);
        $db->method('commit')->willReturn(null);
        $db->method('rollback')->willReturn(null);
        Registry::getInstance()->add('db', $db);

        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willReturn((object) [
            'error' => false,
            'result' => (object) [
                'type' => 'itinerary_dateless',
                'steps' => [
                    (object) [
                        'id' => 42029,
                        'type' => 'course_port',
                        'sections' => [
                            (object) [
                                'id' => 'DF66F264-52D1-6DD4-E24D-633DD2EB1907',
                                'name' => 'default',
                                'varname' => '',
                                'language' => '',
                                'content' => (object) [
                                    'headline' => '1. Tag:',
                                    'description' => 'Beschreibung',
                                ],
                                'tags' => ['Hafen', 'Anreise'],
                            ],
                        ],
                        'board' => [
                            (object) [
                                'breakfast' => 0,
                                'lunch' => 0,
                                'dinner' => 0,
                                'distance' => '456',
                            ],
                        ],
                        'geopoints' => [],
                        'ports' => [],
                        'document_media_objects' => [],
                        'text_media_objects' => [],
                    ],
                ],
            ],
        ]);
        Registry::getInstance()->add('rest_client', $client);

        $import = new Itinerary(869750);
        $import->import();

        $this->assertSame('456', $inserted['pmt2core_itinerary_step_boards'][0]['distance']);
        $this->assertSame('Hafen,Anreise', $inserted['pmt2core_itinerary_step_sections'][0]['tags']);
    }
}
