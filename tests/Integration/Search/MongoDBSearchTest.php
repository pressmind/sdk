<?php

namespace Pressmind\Tests\Integration\Search;

use Pressmind\Tests\Integration\AbstractIntegrationTestCase;
use Pressmind\Tests\Integration\FixtureLoader;

/**
 * E2E integration tests for MongoDB Search (Query -> Aggregation -> Results).
 * Skipped when MongoDB is not configured.
 */
class MongoDBSearchTest extends AbstractIntegrationTestCase
{
    private const TEST_COLLECTION = 'test_search';

    private function loadAndInsertFixture(string $filename): void
    {
        $fixture = FixtureLoader::loadJsonFixture($filename, 'mongodb');
        $fixture = FixtureLoader::resolveDynamicDates($fixture);
        $collection = $this->mongoDb->selectCollection(self::TEST_COLLECTION);
        $collection->insertOne($fixture);
    }

    private function dropTestCollection(): void
    {
        $this->mongoDb->selectCollection(self::TEST_COLLECTION)->drop();
    }

    protected function tearDown(): void
    {
        if ($this->mongoDb !== null) {
            $this->dropTestCollection();
        }
        parent::tearDown();
    }

    public function testSearchByPriceRange(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }

        $this->dropTestCollection();
        $this->loadAndInsertFixture('search_document_standard.json');
        $this->loadAndInsertFixture('search_document_earlybird.json');

        $collection = $this->mongoDb->selectCollection(self::TEST_COLLECTION);

        $count = $collection->countDocuments();
        $this->assertSame(2, $count);

        $belowThreshold = $collection->countDocuments([
            'best_price_meta.price_total' => ['$lte' => 800.0],
        ]);
        $this->assertSame(1, $belowThreshold, 'Only earlybird document has best_price below 800');

        $aboveThreshold = $collection->countDocuments([
            'best_price_meta.price_total' => ['$gte' => 850.0],
        ]);
        $this->assertSame(1, $aboveThreshold, 'Only standard document has best_price at or above 850');
    }

    public function testSearchByTransportType(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }

        $this->dropTestCollection();
        $this->loadAndInsertFixture('search_document_multi_transport.json');

        $collection = $this->mongoDb->selectCollection(self::TEST_COLLECTION);

        $expectedTypes = ['BUS', 'PKW', 'FLUG', 'BAH', 'SCH'];
        foreach ($expectedTypes as $type) {
            $count = $collection->countDocuments([
                'prices.transport_type' => $type,
            ]);
            $this->assertSame(1, $count, "Document should contain transport type {$type}");
        }

        $noMatch = $collection->countDocuments([
            'prices.transport_type' => 'NONEXISTENT',
        ]);
        $this->assertSame(0, $noMatch);
    }

    public function testSearchByDateRange(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }

        $this->dropTestCollection();
        $this->loadAndInsertFixture('search_document_standard.json');

        $collection = $this->mongoDb->selectCollection(self::TEST_COLLECTION);

        $futureDate = FixtureLoader::resolveDate(25)->format('Y-m-d');
        $withinRange = $collection->countDocuments([
            'prices.date_departures' => ['$gte' => $futureDate],
        ]);
        $this->assertSame(1, $withinRange, 'Document has departures beyond +25 days');

        $farFuture = FixtureLoader::resolveDate(365)->format('Y-m-d');
        $outOfRange = $collection->countDocuments([
            'prices.date_departures' => ['$gte' => $farFuture],
        ]);
        $this->assertSame(0, $outOfRange, 'No departures a year from now');
    }

    public function testDocumentStructureMatchesExpected(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }

        $this->dropTestCollection();
        $this->loadAndInsertFixture('search_document_standard.json');

        $collection = $this->mongoDb->selectCollection(self::TEST_COLLECTION);
        $doc = $collection->findOne(['_id' => 100001]);

        $this->assertNotNull($doc, 'Document must exist');

        $requiredKeys = [
            'id_media_object',
            'id_object_type',
            'code',
            'url',
            'description',
            'categories',
            'prices',
            'best_price_meta',
            'departure_date_count',
            'possible_durations',
        ];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, (array) $doc, "Missing required key: {$key}");
        }

        $this->assertSame(100001, $doc['id_media_object']);
        $this->assertSame('TEST-001', $doc['code']);
        $this->assertCount(2, $doc['prices']);

        $firstPrice = (array) $doc['prices'][0];
        $priceKeys = ['price_total', 'duration', 'occupancy', 'transport_type', 'state', 'date_departures'];
        foreach ($priceKeys as $key) {
            $this->assertArrayHasKey($key, $firstPrice, "Price missing key: {$key}");
        }
    }
}
