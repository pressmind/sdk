<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\Import\Itinerary;
use Pressmind\ORM\Object\Itinerary\Map;
use Pressmind\ORM\Object\Itinerary\Step;
use Pressmind\ORM\Object\Itinerary\Step\Geopoint;
use Pressmind\Registry;
use Pressmind\REST\Client;
use Pressmind\Tests\Unit\AbstractTestCase;

class ItineraryGeoTest extends AbstractTestCase
{
    public static function tracks(): array
    {
        return ['type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature', 'id' => 'track-1',
            'properties' => ['name' => 'Höhentour', 'color' => '#ff0000', 'stations' => [['name' => 'Gipfel', 'coordinates' => [13.5, 52.5, 512.75]]]],
            'geometry' => ['type' => 'MultiLineString', 'coordinates' => [[[13, 52.5, 40.25], [14, 52.5, 512.75]]]],
        ]]];
    }

    public function testStepAndPointAcceptNewFieldsAndPreserveAltitude(): void
    {
        $step = new Step();
        $step->fromStdClass((object) ['gpx_tracks' => json_decode(json_encode(self::tracks()))]);
        $this->assertSame(self::tracks(), $step->gpx_tracks);
        $point = new Geopoint();
        $point->fromStdClass((object) ['pointType' => 'accommodation']);
        $this->assertSame('accommodation', $point->toStdClass()->pointType);
    }

    public function testImportPersistsBothItineraryTypesAndReplacesMap(): void
    {
        $stored = [];
        $db = $this->createMock(AdapterInterface::class);
        $db->method('fetchAll')->willReturn([]);
        $db->method('insert')->willReturnCallback(function ($table, $data) use (&$stored) {
            $stored[$table][] = $data;
            return $data['id'] ?? 1;
        });
        Registry::getInstance()->add('db', $db);
        $step = [
            'id' => 42, 'type' => 'course', 'order' => 0,
            'sections' => [], 'text_media_objects' => [], 'document_media_objects' => [],
            'geopoints' => [['lat' => 52.5, 'lng' => 13.5, 'pointType' => 'attraction']],
            'gpx_tracks' => self::tracks(),
        ];
        $responses = [
            ['type' => 'itinerary_dateless', 'steps' => [$step], 'map' => self::tracks()],
            ['type' => 'itinerary_to_touristic', 'variants' => [[
                'id' => 7, 'id_booking_packages' => 8, 'steps' => [$step],
            ]], 'map' => self::tracks()],
        ];
        foreach ($responses as $result) {
            $client = $this->createMock(Client::class);
            $client->method('sendRequest')->willReturn(json_decode(json_encode(['error' => false, 'result' => $result])));
            Registry::getInstance()->add('rest_client', $client);
            $import = new Itinerary(100);
            $import->import();
            $this->assertSame([], $import->getErrors());
        }
        $this->assertCount(2, $stored['pmt2core_itinerary_maps']);
        foreach ($stored['pmt2core_itinerary_maps'] as $row) {
            $this->assertSame(100, $row['id']);
            $this->assertSame(self::tracks(), json_decode($row['geojson'], true));
            $this->assertNotEmpty(json_decode($row['search_geometry'], true)['geometries']);
        }
        foreach ($stored['pmt2core_itinerary_steps'] as $row) {
            $this->assertSame(self::tracks(), json_decode($row['gpx_tracks'], true));
        }
    }

    public function testSuccessfulEmptyResponseClearsMapButApiFailureDoesNot(): void
    {
        $db = $this->createMock(AdapterInterface::class);
        $db->method('fetchAll')->willReturn([]);
        $db->expects($this->once())->method('delete')->with('pmt2core_itinerary_maps', ['id = ?', 100]);
        Registry::getInstance()->add('db', $db);
        $client = $this->createMock(Client::class);
        $client->method('sendRequest')->willReturnOnConsecutiveCalls(
            (object) ['error' => true, 'msg' => 'Unavailable'],
            (object) ['error' => false, 'result' => []]
        );
        Registry::getInstance()->add('rest_client', $client);
        (new Itinerary(100))->import();
        (new Itinerary(100))->import();
    }

    public function testMapRegisteredForSchemaMigration(): void
    {
        $this->assertContains('\\Itinerary\\Map', \Pressmind\System\Info::STATIC_MODELS);
        $map = new Map();
        $this->assertSame('json', $map->getPropertyDefinition('geojson')['type']);
    }
}
