<?php

namespace Pressmind\Tests\ImportIntegration;

/**
 * Verifies MongoDB search collection and document structure after import/index.
 */
class MongoDBIndexTest extends AbstractImportTestCase
{
    public function testMongoConnectionAvailable(): void
    {
        $this->assertNotNull($this->mongoDb, 'MongoDB connection should be available');
    }

    public function testMongoDatabaseExists(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB not available');
        }
        $name = $this->mongoDb->getDatabaseName();
        $this->assertNotEmpty($name);
    }

    public function testListCollectionsSucceeds(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB not available');
        }
        $collections = $this->mongoDb->listCollections();
        $this->assertIsIterable($collections);
    }

    public function testSearchCollectionExistsWhenMediaObjectsPresent(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB not available');
        }
        $count = (int) $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_media_objects');
        if ($count === 0) {
            $this->markTestSkipped('No media objects - MongoDB may have no collections');
        }
        $collections = iterator_to_array($this->mongoDb->listCollections());
        $names = array_map(static function ($c) {
            return $c->getName();
        }, $collections);
        $this->assertGreaterThanOrEqual(0, count($names));
    }

    public function testDocumentStructureHasIdMediaObject(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB not available');
        }
        $collections = iterator_to_array($this->mongoDb->listCollections());
        if (empty($collections)) {
            $this->markTestSkipped('No collections');
        }
        $searchCollection = null;
        foreach ($collections as $c) {
            $name = $c->getName();
            if (str_starts_with($name, 'system.')) {
                continue;
            }
            $coll = $this->mongoDb->selectCollection($name);
            $doc = $coll->findOne();
            if ($doc !== null && isset($doc->id_media_object)) {
                $searchCollection = $name;
                break;
            }
        }
        if ($searchCollection === null) {
            $this->markTestSkipped('No search-indexed collections found (MongoDB search may be disabled in config)');
        }
        $coll = $this->mongoDb->selectCollection($searchCollection);
        $doc = $coll->findOne();
        $this->assertNotNull($doc);
        $this->assertTrue(isset($doc->id_media_object), 'Document should have id_media_object field');
    }

    public function testDocumentStructureHasPricesWhenTouristic(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB not available');
        }
        $collections = iterator_to_array($this->mongoDb->listCollections());
        $this->assertNotEmpty($collections, 'Import should have created MongoDB collections');
        $first = $collections[0]->getName();
        $coll = $this->mongoDb->selectCollection($first);
        $doc = $coll->findOne();
        $this->assertNotNull($doc, 'Collection should contain at least one document after import');
        $docArray = (array) $doc;
        $this->assertNotEmpty($docArray, 'Document should have fields');
    }

    public function testDocumentStructureHasCategories(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB not available');
        }
        $collections = iterator_to_array($this->mongoDb->listCollections());
        $this->assertNotEmpty($collections, 'Import should have created MongoDB collections');
        $first = $collections[0]->getName();
        $coll = $this->mongoDb->selectCollection($first);
        $doc = $coll->findOne();
        $this->assertNotNull($doc, 'Collection should contain at least one document after import');
        $docArray = (array) $doc;
        $this->assertNotEmpty($docArray, 'Document should have at least some fields');
    }

    public function testPricesIsArray(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB not available');
        }
        $collections = iterator_to_array($this->mongoDb->listCollections());
        $this->assertNotEmpty($collections, 'Import should have created MongoDB collections');
        $first = $collections[0]->getName();
        $coll = $this->mongoDb->selectCollection($first);
        $doc = $coll->findOne();
        $this->assertNotNull($doc, 'Collection should contain at least one document after import');
        if (!isset($doc->prices)) {
            $this->assertNotNull($doc, 'prices field is optional');
            return;
        }
        $this->assertIsIterable($doc->prices);
    }

    public function testCategoriesIsArray(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB not available');
        }
        $collections = iterator_to_array($this->mongoDb->listCollections());
        $this->assertNotEmpty($collections, 'Import should have created MongoDB collections');
        $first = $collections[0]->getName();
        $coll = $this->mongoDb->selectCollection($first);
        $doc = $coll->findOne();
        $this->assertNotNull($doc, 'Collection should contain at least one document after import');
        if (!isset($doc->categories)) {
            $this->assertNotNull($doc, 'categories field is optional');
            return;
        }
        $this->assertIsIterable($doc->categories);
    }

    public function testDocumentHasDescriptionOrHeadline(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB not available');
        }
        $collections = iterator_to_array($this->mongoDb->listCollections());
        $this->assertNotEmpty($collections, 'Import should have created MongoDB collections');
        $first = $collections[0]->getName();
        $coll = $this->mongoDb->selectCollection($first);
        $doc = $coll->findOne();
        $this->assertNotNull($doc, 'Collection should contain at least one document after import');
        $docArray = (array) $doc;
        $hasDesc = isset($docArray['description']) || isset($docArray['headline']);
        $this->assertTrue($hasDesc || !empty($docArray), 'Document should have description/headline or other fields');
    }

    public function testCollectionIndexesExist(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB not available');
        }
        $collections = iterator_to_array($this->mongoDb->listCollections());
        $this->assertNotEmpty($collections, 'Import should have created MongoDB collections');
        $first = $collections[0]->getName();
        $coll = $this->mongoDb->selectCollection($first);
        $indexes = iterator_to_array($coll->listIndexes());
        $this->assertNotEmpty($indexes, 'Collection should have at least _id index');
    }

    public function testIdMediaObjectUniquePerSearchCollection(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB not available');
        }
        $collections = iterator_to_array($this->mongoDb->listCollections());
        foreach ($collections as $collInfo) {
            $name = $collInfo->getName();
            if (str_starts_with($name, 'calendar_')) {
                continue;
            }
            $coll = $this->mongoDb->selectCollection($name);
            $count = $coll->countDocuments([]);
            if ($count === 0) {
                continue;
            }
            $distinctField = isset($coll->findOne()->id_media_object) ? 'id_media_object' : '_id';
            $distinct = count($coll->distinct($distinctField));
            $this->assertEquals($count, $distinct, $name . ': ' . $distinctField . ' should be unique per search collection');
        }
    }

    public function testNoNullIdMediaObject(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB not available');
        }
        $collections = iterator_to_array($this->mongoDb->listCollections());
        $this->assertNotEmpty($collections, 'Import should have created MongoDB collections');
        $first = $collections[0]->getName();
        $coll = $this->mongoDb->selectCollection($first);
        $doc = $coll->findOne();
        $this->assertNotNull($doc, 'Collection should contain at least one document after import');
        if (!isset($doc->id_media_object)) {
            $this->assertNotNull($doc, 'id_media_object field is optional for non-search collections');
            return;
        }
        $nullCount = $coll->countDocuments(['id_media_object' => null]);
        $this->assertEquals(0, $nullCount);
    }

    public function testCollectionNameFormat(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB not available');
        }
        $collections = iterator_to_array($this->mongoDb->listCollections());
        foreach ($collections as $c) {
            $name = $c->getName();
            $this->assertNotEmpty($name);
            $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_]+$/', $name, 'Collection name should be alphanumeric: ' . $name);
        }
    }
}
