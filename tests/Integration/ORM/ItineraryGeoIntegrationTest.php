<?php

namespace Pressmind\Tests\Integration\ORM;

use Pressmind\DB\Scaffolder\Mysql;
use Pressmind\Import\Itinerary;
use Pressmind\ORM\Object\Itinerary\Map;
use Pressmind\ORM\Object\Itinerary\Step;
use Pressmind\ORM\Object\Itinerary\Step\Geopoint;
use Pressmind\Registry;
use Pressmind\REST\Client;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;
use Pressmind\Tests\Unit\Import\ItineraryGeoTest;

class ItineraryGeoIntegrationTest extends AbstractIntegrationTestCase
{
    private array $createdTables = [];

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->db === null) {
            $this->markTestSkipped('MySQL required');
        }
        // Separate test database required, as for the other ORM integration tests.
        foreach (\Pressmind\System\Info::STATIC_MODELS as $name) {
            if (strpos($name, '\\Itinerary\\') !== 0) {
                continue;
            }
            $class = '\\Pressmind\\ORM\\Object' . $name;
            $model = new $class();
            if ($this->db->fetchOne('SHOW TABLES LIKE ?', [$model->getDbTableName()]) === null) {
                (new Mysql($model))->run();
                $this->createdTables[] = $model->getDbTableName();
            }
        }
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            foreach ($this->createdTables as $table) {
                $this->db->execute('DROP TABLE `' . $table . '`');
            }
        }
        parent::tearDown();
    }

    public function testImportReadReimportAndRemoval(): void
    {
        $id = 990235;
        $step = ['id' => $id, 'type' => 'course', 'sections' => [], 'text_media_objects' => [], 'document_media_objects' => [],
            'geopoints' => [['lat' => 52.5, 'lng' => 13.5, 'pointType' => 'food']],
            'gpx_tracks' => ItineraryGeoTest::tracks()];
        $responses = [
            ['type' => 'itinerary_dateless', 'steps' => [$step], 'map' => ItineraryGeoTest::tracks()],
            ['type' => 'itinerary_dateless', 'steps' => [array_replace($step, ['gpx_tracks' => ['type' => 'FeatureCollection', 'features' => []]])],
                'map' => ['type' => 'FeatureCollection', 'features' => []]],
            [],
        ];
        $client = $this->createMock(Client::class);
        $client->method('sendRequest')->willReturnOnConsecutiveCalls(...array_map(static fn($result) => json_decode(json_encode(['error' => false, 'result' => $result])), $responses));
        Registry::getInstance()->add('rest_client', $client);
        (new Itinerary($id))->import();
        $this->assertSame(ItineraryGeoTest::tracks(), (new Step($id))->gpx_tracks);
        $this->assertSame(ItineraryGeoTest::tracks(), (new Map($id))->geojson);
        $this->assertSame('food', Geopoint::listAll(['id_step' => $id])[0]->pointType);
        $mediaObject = new \Pressmind\ORM\Object\MediaObject();
        $mediaObject->id = $id;
        $this->assertSame(ItineraryGeoTest::tracks(), $mediaObject->getItineraryMap());
        (new Itinerary($id))->import();
        $this->assertSame([], (new Step($id))->gpx_tracks['features']);
        $this->assertSame([], (new Map($id))->geojson['features']);
        $this->assertCount(1, (new Map($id))->search_geometry['geometries']);
        (new Itinerary($id))->import();
        $this->assertNull((new Map($id))->geojson);
        $this->assertSame([], Step::listAll(['id_media_object' => $id]));
        // Remove only this test's records when tables pre-existed.
        foreach (Step::listAll(['id_media_object' => $id]) as $oldStep) {
            $oldStep->delete(true);
        }
    }
    public function testSchemaUpgradeAndLargeTrackRoundTrip(): void
    {
        if (!in_array('pmt2core_itinerary_steps', $this->createdTables, true)
            || !in_array('pmt2core_itinerary_step_geopoints', $this->createdTables, true)) {
            $this->markTestSkipped('Migration simulation requires tables created by this test');
        }
        $this->db->execute('ALTER TABLE pmt2core_itinerary_steps DROP COLUMN gpx_tracks');
        $this->db->execute('ALTER TABLE pmt2core_itinerary_step_geopoints DROP COLUMN pointType');
        $apply = new \ReflectionMethod(\Pressmind\CLI\DatabaseIntegrityCheckCommand::class, 'applyStaticModelDifferences');
        $apply->setAccessible(true);
        foreach ([new Step(), new Geopoint()] as $model) {
            $differences = (new \Pressmind\DB\IntegrityCheck\Mysql($model))->check();
            $this->assertIsArray($differences);
            ob_start();
            try {
                $apply->invoke(new \Pressmind\CLI\DatabaseIntegrityCheckCommand(), $model, get_class($model), $differences);
            } finally {
                ob_end_clean();
            }
            $this->assertTrue((new \Pressmind\DB\IntegrityCheck\Mysql($model))->check());
        }
        $track = ItineraryGeoTest::tracks();
        $coordinates = [];
        for ($i = 0; $i < 5000; $i++) {
            $coordinates[] = [13 + $i / 100000, 52.5, 100.125 + $i / 100];
        }
        $track['features'][0]['geometry']['coordinates'] = [$coordinates];
        $this->assertGreaterThan(65535, strlen(json_encode($track)));
        $step = new Step();
        $step->id = 990236;
        $step->gpx_tracks = $track;
        $step->create();
        $this->assertEquals($track, (new Step(990236))->gpx_tracks);
        $point = new Geopoint();
        $point->id_step = 990236;
        $point->pointType = 'accommodation';
        $point->create();
        $this->assertSame('accommodation', (new Geopoint($point->id))->pointType);
    }

}
