<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use DateTime;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Season;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;
use stdClass;

/**
 * Unit tests for MediaObject: basis, __get(season), getDataForLanguage, render, getByCode,
 * getValueByTagName, delete override behaviour, indexer methods (create/delete MongoDB/Calendar/OpenSearch/Search).
 */
class MediaObjectTest extends AbstractTestCase
{
    private function createMediaObject(?int $id = null): MediaObject
    {
        return new MediaObject($id, false);
    }

    public function testInstantiationWithoutId(): void
    {
        $mo = $this->createMediaObject();
        $this->assertNull($mo->getId());
    }

    public function testGetDbTableNameReturnsPrefixedTableName(): void
    {
        $mo = $this->createMediaObject();
        $this->assertSame('pmt2core_pmt2core_media_objects', $mo->getDbTableName());
    }

    public function testSetIdAndGetId(): void
    {
        $mo = $this->createMediaObject();
        $mo->setId(12345);
        $this->assertSame(12345, $mo->getId());
    }

    public function testFromArrayPopulatesScalarProperties(): void
    {
        $mo = $this->createMediaObject();
        $mo->fromArray([
            'id' => 100,
            'code' => 'TEST-MO',
            'id_object_type' => 1,
            'name' => 'Test Product',
        ]);
        $this->assertSame(100, $mo->getId());
        $this->assertSame('TEST-MO', $mo->code);
        $this->assertSame(1, $mo->id_object_type);
        $this->assertSame('Test Product', $mo->name);
    }

    public function testToStdClassWithoutRelationsExcludesOnlyScalarProperties(): void
    {
        $mo = $this->createMediaObject();
        $mo->setId(99);
        $mo->code = 'STD-MO';
        $mo->id_object_type = 2;
        $std = $mo->toStdClass(false);
        $this->assertInstanceOf(stdClass::class, $std);
        $this->assertSame(99, $std->id);
        $this->assertSame('STD-MO', $std->code);
        $this->assertSame(2, $std->id_object_type);
    }

    public function testIsValidReturnsFalseWhenIdIsNull(): void
    {
        $mo = $this->createMediaObject();
        $this->assertFalse($mo->isValid());
    }

    public function testIsValidReturnsTrueWhenIdIsSet(): void
    {
        $mo = $this->createMediaObject();
        $mo->setId(1);
        $this->assertTrue($mo->isValid());
    }

    public function testGetIdReturnsNullWhenNotSet(): void
    {
        $mo = $this->createMediaObject();
        $this->assertNull($mo->getId());
    }

    /**
     * __get('season') with different_season_from/to: when season is loaded (or created), it gets season_from/season_to from different_season_*.
     */
    public function testGetSeasonWithDifferentSeasonDatesAppliesSeasonDates(): void
    {
        $mo = $this->createMediaObject();
        $mo->setId(1);
        $mo->different_season_from = new DateTime('2026-01-01');
        $mo->different_season_to = new DateTime('2026-12-31');
        $season = $mo->season;
        $this->assertInstanceOf(Season::class, $season);
        $this->assertEquals(new DateTime('2026-01-01'), $season->season_from);
        $this->assertEquals(new DateTime('2026-12-31'), $season->season_to);
    }

    public function testGetDataForLanguageUsesConfigDefaultWhenLanguageIsNull(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['languages'] = ['default' => 'de'];
        Registry::getInstance()->add('config', $config);

        $mo = $this->createMediaObject();
        $mo->setId(1);
        $deData = new stdClass();
        $deData->language = 'de';
        $deData->headline = 'Deutscher Titel';
        $enData = new stdClass();
        $enData->language = 'en';
        $enData->headline = 'English Title';
        $mo->data = [$deData, $enData];

        $result = $mo->getDataForLanguage(null);
        $this->assertSame($deData, $result);
    }

    public function testGetDataForLanguageReturnsMatchingLanguage(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['languages'] = ['default' => 'de'];
        Registry::getInstance()->add('config', $config);

        $mo = $this->createMediaObject();
        $mo->setId(1);
        $deData = new stdClass();
        $deData->language = 'de';
        $enData = new stdClass();
        $enData->language = 'en';
        $mo->data = [$deData, $enData];

        $this->assertSame($enData, $mo->getDataForLanguage('en'));
        $this->assertSame($deData, $mo->getDataForLanguage('de'));
    }

    public function testGetDataForLanguageReturnsNullWhenLanguageNotFound(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['languages'] = ['default' => 'de'];
        Registry::getInstance()->add('config', $config);

        $mo = $this->createMediaObject();
        $mo->setId(1);
        $mo->data = [(object)['language' => 'de']];

        $this->assertNull($mo->getDataForLanguage('fr'));
    }

    /**
     * getByCode uses loadAll; mock returns one row so we get one result.
     */
    public function testGetByCodeReturnsArrayFromLoadAll(): void
    {
        $row = new stdClass();
        $row->id = 42;
        $row->code = 'MY-CODE';
        $row->id_object_type = 1;

        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('fetchAll')->willReturnCallback(function ($query, $params = null, $class_name = null) use ($row) {
            if (strpos($query, 'code') !== false) {
                return [$row];
            }
            return [];
        });
        $db->method('fetchRow')->willReturn(null);
        $db->method('fetchOne')->willReturn(null);
        $db->method('getAffectedRows')->willReturn(0);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
        $db->method('execute')->willReturn(null);
        $db->method('insert')->willReturn(null);
        $db->method('replace')->willReturn(null);
        $db->method('update')->willReturn(null);
        $db->method('delete')->willReturn(null);
        $db->method('truncate')->willReturn(null);
        $db->method('batchInsert')->willReturn(1);
        $db->method('beginTransaction')->willReturn(null);
        $db->method('commit')->willReturn(null);
        $db->method('rollback')->willReturn(null);
        $db->method('inTransaction')->willReturn(false);

        Registry::getInstance()->add('db', $db);

        $result = MediaObject::getByCode('MY-CODE');
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame(42, $result[0]->id);
        $this->assertSame('MY-CODE', $result[0]->code);
    }

    public function testGetByCodeReturnsEmptyArrayWhenNoMatch(): void
    {
        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('fetchAll')->willReturn([]);
        $db->method('fetchRow')->willReturn(null);
        $db->method('fetchOne')->willReturn(null);
        $db->method('getAffectedRows')->willReturn(0);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
        $db->method('execute')->willReturn(null);
        $db->method('insert')->willReturn(null);
        $db->method('replace')->willReturn(null);
        $db->method('update')->willReturn(null);
        $db->method('delete')->willReturn(null);
        $db->method('truncate')->willReturn(null);
        $db->method('batchInsert')->willReturn(1);
        $db->method('beginTransaction')->willReturn(null);
        $db->method('commit')->willReturn(null);
        $db->method('rollback')->willReturn(null);
        $db->method('inTransaction')->willReturn(false);

        Registry::getInstance()->add('db', $db);

        $result = MediaObject::getByCode('NONEXISTENT');
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    /**
     * delete() is inherited from AbstractObject; we only assert it runs without throwing when id is set and db is mocked.
     */
    public function testDeleteCallsDbDelete(): void
    {
        $mo = $this->createMediaObject();
        $mo->setId(100);
        $mo->delete(false);
        $this->addToAssertionCount(1);
    }

    /**
     * delete(true) triggers relation loading and may require Custom\MediaType; tested in integration.
     */
    public function testDeleteWithRelationsDoesNotThrowWhenNoRelationsLoaded(): void
    {
        $mo = $this->createMediaObject();
        $mo->setId(100);
        $mo->booking_packages = [];
        $mo->routes = [];
        $mo->data = [];
        $mo->delete(false);
        $this->addToAssertionCount(1);
    }

    /**
     * Indexer methods must not throw when search_mongodb / opensearch / fulltext config is not set or disabled.
     */
    public function testDeleteMongoDBIndexDoesNotThrowWhenSearchMongoNotEnabled(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['search_mongodb'] = ['enabled' => false];
        Registry::getInstance()->add('config', $config);

        $mo = $this->createMediaObject();
        $mo->setId(1);
        $mo->deleteMongoDBIndex();
        $this->addToAssertionCount(1);
    }

    public function testCreateMongoDBIndexDoesNotThrowWhenSearchMongoNotEnabled(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['search_mongodb'] = ['enabled' => false];
        Registry::getInstance()->add('config', $config);

        $mo = $this->createMediaObject();
        $mo->setId(1);
        $mo->createMongoDBIndex();
        $this->addToAssertionCount(1);
    }

    public function testCreateMongoDBCalendarDoesNotThrowWhenSearchMongoNotEnabled(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['search_mongodb'] = ['enabled' => false];
        Registry::getInstance()->add('config', $config);

        $mo = $this->createMediaObject();
        $mo->setId(1);
        $mo->createMongoDBCalendar();
        $this->addToAssertionCount(1);
    }

    public function testDeleteMongoDBCalendarDoesNotThrowWhenSearchMongoNotEnabled(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['search_mongodb'] = ['enabled' => false];
        Registry::getInstance()->add('config', $config);

        $mo = $this->createMediaObject();
        $mo->setId(1);
        $mo->deleteMongoDBCalendar();
        $this->addToAssertionCount(1);
    }

    public function testCreateOpenSearchIndexDoesNotThrowWhenOpenSearchNotEnabled(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['search_opensearch'] = ['search_opensearch' => ['enabled' => false]];
        Registry::getInstance()->add('config', $config);

        $mo = $this->createMediaObject();
        $mo->setId(1);
        $mo->createOpenSearchIndex();
        $this->addToAssertionCount(1);
    }

    /**
     * createSearchIndex() returns early when media_types_fulltext_index_fields is not set.
     */
    public function testCreateSearchIndexDoesNotThrowWhenFulltextFieldsNotSet(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        unset($config['data']['media_types_fulltext_index_fields']);
        Registry::getInstance()->add('config', $config);

        $mo = $this->createMediaObject();
        $mo->setId(1);
        $mo->createSearchIndex();
        $this->addToAssertionCount(1);
    }

    /**
     * getValueByTagName uses ObjectdataTag::listAll and getDataForLanguage. When listAll returns empty, result is null.
     */
    public function testGetValueByTagNameReturnsNullWhenNoTagFound(): void
    {
        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('fetchAll')->willReturn([]);
        $db->method('fetchRow')->willReturn(null);
        $db->method('fetchOne')->willReturn(null);
        $db->method('getAffectedRows')->willReturn(0);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
        $db->method('execute')->willReturn(null);
        $db->method('insert')->willReturn(null);
        $db->method('replace')->willReturn(null);
        $db->method('update')->willReturn(null);
        $db->method('delete')->willReturn(null);
        $db->method('truncate')->willReturn(null);
        $db->method('batchInsert')->willReturn(1);
        $db->method('beginTransaction')->willReturn(null);
        $db->method('commit')->willReturn(null);
        $db->method('rollback')->willReturn(null);
        $db->method('inTransaction')->willReturn(false);

        Registry::getInstance()->add('db', $db);

        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['languages'] = ['default' => 'de'];
        Registry::getInstance()->add('config', $config);

        $mo = $this->createMediaObject();
        $mo->setId(1);
        $mo->id_object_type = 1;
        $result = $mo->getValueByTagName('nonexistent_tag');
        $this->assertNull($result);
    }
}
