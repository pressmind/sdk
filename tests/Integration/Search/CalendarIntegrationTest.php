<?php

namespace Pressmind\Tests\Integration\Search;

use Pressmind\Registry;
use Pressmind\Search\MongoDB;
use Pressmind\Search\MongoDB\Calendar;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for MongoDB Calendar: getCollectionName (calendar_ prefix),
 * deleteMediaObject, createCollectionIndex (no-op), and inherited collection management.
 *
 * Requires real MongoDB connection (skipped otherwise).
 */
class CalendarIntegrationTest extends AbstractIntegrationTestCase
{
    private const TEST_PREFIX = 'calendar_test_';

    private ?Calendar $calendar = null;

    private array $createdCollections = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->addSearchConfigToRegistry();
        MongoDB::clearConnectionCache();

        if ($this->db === null || $this->mongoDb === null) {
            return;
        }

        $this->calendar = new Calendar();
    }

    protected function tearDown(): void
    {
        if ($this->mongoDb !== null) {
            foreach ($this->createdCollections as $name) {
                try {
                    $this->mongoDb->selectCollection($name)->drop();
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }
        MongoDB::clearConnectionCache();
        parent::tearDown();
    }

    private function addSearchConfigToRegistry(): void
    {
        $mongoUri = getenv('MONGODB_URI');
        $mongoDb = getenv('MONGODB_DB');
        if (empty($mongoUri) || empty($mongoDb)) {
            return;
        }
        $config = $this->getIntegrationConfig();
        $config['data'] = [
            'search_mongodb' => [
                'database' => ['uri' => $mongoUri, 'db' => $mongoDb],
                'search' => [
                    'build_for' => [],
                    'touristic' => [
                        'occupancies' => [1, 2],
                        'duration_ranges' => [[1, 7], [8, 14]],
                    ],
                    'calendar' => [],
                ],
            ],
            'touristic' => [],
            'media_types_allowed_visibilities' => [],
            'media_types_fulltext_index_fields' => [],
        ];
        Registry::getInstance()->add('config', $config);
    }

    private function requireMongo(): void
    {
        if ($this->mongoDb === null || $this->calendar === null) {
            $this->markTestSkipped('MongoDB required');
        }
    }

    private function trackCollection(string $name): void
    {
        $this->createdCollections[] = $name;
    }

    private function collectionExists(string $name): bool
    {
        foreach ($this->mongoDb->listCollections() as $coll) {
            if ($coll->getName() === $name) {
                return true;
            }
        }
        return false;
    }

    public function testGetCollectionNameDefault(): void
    {
        $this->requireMongo();
        $this->assertSame('calendar_origin_0', $this->calendar->getCollectionName(0, null, null));
    }

    public function testGetCollectionNameWithLanguage(): void
    {
        $this->requireMongo();
        $this->assertSame('calendar_de_origin_0', $this->calendar->getCollectionName(0, 'de', null));
        $this->assertSame('calendar_en_origin_1', $this->calendar->getCollectionName(1, 'en', null));
    }

    public function testGetCollectionNameWithAgency(): void
    {
        $this->requireMongo();
        $this->assertSame('calendar_de_origin_0_agency_AG1', $this->calendar->getCollectionName(0, 'de', 'AG1'));
    }

    public function testDeleteMediaObjectRemovesFromCalendarCollections(): void
    {
        $this->requireMongo();
        $mongoUri = getenv('MONGODB_URI');
        $mongoDbName = getenv('MONGODB_DB');
        if (empty($mongoUri) || empty($mongoDbName)) {
            $this->markTestSkipped('MongoDB env required');
        }
        $collectionName = 'calendar_de_origin_0';
        $this->trackCollection($collectionName);

        $config = $this->getIntegrationConfig();
        $config['data'] = [
            'search_mongodb' => [
                'database' => ['uri' => $mongoUri, 'db' => $mongoDbName],
                'search' => [
                    'build_for' => [
                        1 => [['origin' => 0, 'language' => 'de']],
                    ],
                    'touristic' => [
                        'occupancies' => [1, 2],
                        'duration_ranges' => [[1, 7], [8, 14]],
                    ],
                    'calendar' => [],
                ],
            ],
            'touristic' => [],
            'media_types_allowed_visibilities' => [],
            'media_types_fulltext_index_fields' => [],
        ];
        Registry::getInstance()->add('config', $config);
        $calendar = new Calendar();

        $this->mongoDb->createCollection($collectionName);
        $testId = 888881;
        $this->mongoDb->selectCollection($collectionName)->insertMany([
            ['_id' => uniqid('c1'), 'id_media_object' => $testId, 'occupancy' => 2],
            ['_id' => uniqid('c2'), 'id_media_object' => 999, 'occupancy' => 2],
        ]);
        $this->assertSame(1, $this->mongoDb->selectCollection($collectionName)->countDocuments(['id_media_object' => $testId]));
        $this->assertSame(1, $this->mongoDb->selectCollection($collectionName)->countDocuments(['id_media_object' => 999]));

        $calendar->deleteMediaObject($testId);

        $this->assertSame(0, $this->mongoDb->selectCollection($collectionName)->countDocuments(['id_media_object' => $testId]));
        $this->assertSame(1, $this->mongoDb->selectCollection($collectionName)->countDocuments(['id_media_object' => 999]));
    }

    public function testCreateCollectionIndexDoesNotThrow(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . uniqid();
        $this->trackCollection($name);
        $this->mongoDb->createCollection($name);

        $this->calendar->createCollectionIndex($name);
        $this->assertTrue(true, 'createCollectionIndex (empty implementation) must not throw');
    }

    public function testCreateCollectionIfNotExistsAndFlushCollection(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'lifecycle_' . uniqid();
        $this->trackCollection($name);

        $this->assertFalse($this->collectionExists($name));
        $this->calendar->createCollectionIfNotExists($name);
        $this->assertTrue($this->collectionExists($name));

        $this->mongoDb->selectCollection($name)->insertMany([['_id' => 1], ['_id' => 2]]);
        $this->assertSame(2, $this->mongoDb->selectCollection($name)->countDocuments());

        $this->calendar->flushCollection($name);
        $this->assertSame(0, $this->mongoDb->selectCollection($name)->countDocuments());
    }
}
