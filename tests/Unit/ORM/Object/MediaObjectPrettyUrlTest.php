<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\ORM\Object\MediaObject;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\ValueObject\MediaObject\Result\GetPrettyUrls;
use stdClass;

/**
 * Unit tests for MediaObject pretty URL API: buildPrettyUrls, getPrettyUrl, getPrettyUrls, getByPrettyUrl (static).
 */
class MediaObjectPrettyUrlTest extends AbstractTestCase
{
    private function createMediaObject(?int $id = null): MediaObject
    {
        return new MediaObject($id, false);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['languages'] = ['default' => 'de', 'allowed' => ['de', 'en']];
        $config['data']['media_types_pretty_url'] = [
            1 => [
                'fields' => ['name'],
                'separator' => '-',
                'strategy' => 'unique',
                'prefix' => '/',
                'suffix' => '',
            ],
        ];
        $config['data']['media_types'] = [1 => 'pauschalreise'];
        Registry::getInstance()->add('config', $config);
    }

    public function testGetPrettyUrlReturnsNullWhenNoRoutes(): void
    {
        $mo = $this->createMediaObject();
        $mo->setId(1);
        $mo->routes = [];
        $this->assertNull($mo->getPrettyUrl());
    }

    public function testGetPrettyUrlReturnsFirstRouteWhenLanguageNull(): void
    {
        $route1 = new stdClass();
        $route1->id = 1;
        $route1->route = '/my-product';
        $route1->language = 'de';
        $route1->id_media_object = 100;
        $route1->id_object_type = 1;

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $mo->routes = [$route1];
        $this->assertSame('/my-product', $mo->getPrettyUrl(null));
    }

    public function testGetPrettyUrlReturnsLanguagePrefixedRouteWhenLanguageGiven(): void
    {
        $routeDe = new stdClass();
        $routeDe->id = 1;
        $routeDe->route = '/mein-produkt';
        $routeDe->language = 'de';
        $routeEn = new stdClass();
        $routeEn->id = 2;
        $routeEn->route = '/my-product';
        $routeEn->language = 'en';
        $mo = $this->createMediaObject();
        $mo->setId(100);
        $mo->routes = [$routeDe, $routeEn];
        $this->assertSame('/en/my-product', $mo->getPrettyUrl('en'));
    }

    public function testGetPrettyUrlsReturnsArrayOfGetPrettyUrls(): void
    {
        $route1 = new stdClass();
        $route1->id = 1;
        $route1->route = '/product-a';
        $route1->language = 'de';
        $route1->id_media_object = 100;
        $route1->id_object_type = 1;

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $mo->routes = [$route1];
        $result = $mo->getPrettyUrls();
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(GetPrettyUrls::class, $result[0]);
        $this->assertSame('/product-a', $result[0]->route);
        $this->assertSame('de', $result[0]->language);
        $this->assertTrue($result[0]->is_default);
    }

    public function testGetByPrettyUrlReturnsArrayFromDb(): void
    {
        $row = new stdClass();
        $row->id = 42;
        $row->id_object_type = 1;
        $row->visibility = 30;
        $row->language = 'de';

        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('fetchAll')->willReturnCallback(function ($query, $params = null, $class_name = null) use ($row) {
            if (strpos($query, 'pmt2core_media_objects') !== false && strpos($query, 'pmt2core_routes') !== false) {
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

        $result = MediaObject::getByPrettyUrl('/my-route', null, 'de', null);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame(42, $result[0]->id);
        $this->assertSame(1, $result[0]->id_object_type);
    }

    public function testGetByPrettyUrlReturnsEmptyArrayWhenNoMatch(): void
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

        $result = MediaObject::getByPrettyUrl('/nonexistent', null, 'de', null);
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    /**
     * buildPrettyUrls with strategy 'unique' and field 'name': returns one URL when route does not exist.
     */
    public function testBuildPrettyUrlsUniqueStrategyReturnsOneUrl(): void
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

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $mo->id_object_type = 1;
        $mo->name = 'My Test Product';
        $urls = $mo->buildPrettyUrls('de');
        $this->assertIsArray($urls);
        $this->assertCount(1, $urls);
        $this->assertStringStartsWith('/', $urls[0]);
        $this->assertStringContainsString('my-test-product', $urls[0]);
    }

    /**
     * buildPrettyUrls with strategy 'channel' without id_channel throws.
     */
    public function testBuildPrettyUrlsChannelStrategyWithoutIdChannelThrows(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data']['media_types_pretty_url'] = [
            1 => [
                'fields' => ['name'],
                'strategy' => 'channel',
                'id_channel' => null,
                'prefix' => '/',
                'suffix' => '',
            ],
        ];
        Registry::getInstance()->add('config', $config);

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $mo->id_object_type = 1;
        $mo->name = 'Product';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('id_channel');
        $mo->buildPrettyUrls('de');
    }

    /**
     * buildPrettyUrls with strategy 'channel' and pretty_urls_from_api without matching id_channel throws.
     */
    public function testBuildPrettyUrlsChannelStrategyWithNoMatchingChannelThrows(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data']['media_types_pretty_url'] = [
            1 => [
                'fields' => ['name'],
                'strategy' => 'channel',
                'id_channel' => 1,
                'prefix' => '/',
                'suffix' => '',
            ],
        ];
        Registry::getInstance()->add('config', $config);

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $mo->id_object_type = 1;
        $prettyUrlsFromApi = [(object)['id_channel' => 99, 'url' => '/other-channel']];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('no entry found for id_channel');
        $mo->buildPrettyUrls('de', $prettyUrlsFromApi);
    }
}
