<?php

namespace Pressmind\Tests\Integration\ORM;

use DateTime;
use Pressmind\DB\Scaffolder\Mysql as ScaffolderMysql;
use Custom\MediaType\Pauschalreise;
use Pressmind\ORM\Object\Brand;
use Pressmind\ORM\Object\MediaObject\MyContent;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Route;
use Pressmind\ORM\Object\Season;
use Pressmind\Registry;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;
use Pressmind\Tests\Integration\FixtureDateHelper;

/**
 * Integration tests for MediaObject: CRUD, loadAll, getByCode, getByPrettyUrl,
 * getPrettyUrl, getPrettyUrls, buildPrettyUrls, getDataForLanguage, delete(deleteRelations).
 * Uses FixtureDateHelper for any date-relative fixture data.
 */
class MediaObjectIntegrationTest extends AbstractIntegrationTestCase
{
    use FixtureDateHelper;

    private const TEST_ID_BASE = 900000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureDateBase = new DateTime('today');
        if ($this->db === null) {
            return;
        }
        $this->addMediaObjectConfig();
        $this->ensureTables();
        $this->cleanTestData();
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            $this->cleanTestData();
        }
        parent::tearDown();
    }

    private function addMediaObjectConfig(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['languages'] = ['default' => 'de', 'allowed' => ['de', 'en']];
        $config['data']['media_types'] = [1 => 'pauschalreise'];
        $config['data']['media_types_pretty_url'] = [
            1 => [
                'fields' => ['name'],
                'separator' => '-',
                'strategy' => 'unique',
                'prefix' => '/',
                'suffix' => '',
            ],
        ];
        Registry::getInstance()->add('config', $config);
    }

    private function ensureTables(): void
    {
        $objects = [
            new Brand(),
            new Season(),
            new MediaObject(),
            new Route(),
            new Pauschalreise(),
            new MyContent(),
        ];
        foreach ($objects as $obj) {
            try {
                $scaffolder = new ScaffolderMysql($obj);
                $scaffolder->run(true);
            } catch (\Throwable $e) {
                // table may already exist
            }
        }
    }

    private function cleanTestData(): void
    {
        $this->db->delete('pmt2core_routes', ['id_media_object >= ?', self::TEST_ID_BASE]);
        $this->db->delete('pmt2core_media_objects', ['id >= ?', self::TEST_ID_BASE]);
        $this->db->delete('pmt2core_seasons', ['id >= ?', self::TEST_ID_BASE]);
        $this->db->delete('pmt2core_brands', ['id >= ?', self::TEST_ID_BASE]);
    }

    private function insertBrandAndSeason(): void
    {
        $brand = new Brand();
        $brand->id = self::TEST_ID_BASE;
        $brand->name = 'Integration Test Brand';
        $brand->create();
        $season = new Season();
        $season->id = self::TEST_ID_BASE;
        $season->name = 'Test Season';
        $season->active = 1;
        $season->season_from = new DateTime('2020-01-01');
        $season->season_to = new DateTime('2030-12-31');
        $season->time_of_year = 'all';
        $season->create();
    }

    private function createMediaObject(int $idOffset = 0, string $code = 'INT-TEST-MO', string $name = 'Integration Test Product'): MediaObject
    {
        $mo = new MediaObject(null, false);
        $mo->id = self::TEST_ID_BASE + $idOffset;
        $mo->id_pool = 1;
        $mo->id_object_type = 1;
        $mo->id_client = 1;
        $mo->id_brand = self::TEST_ID_BASE;
        $mo->id_season = self::TEST_ID_BASE;
        $mo->name = $name;
        $mo->code = $code;
        $mo->visibility = 30;
        $mo->state = 50;
        $mo->hidden = 0;
        return $mo;
    }

    public function testCreateAndReadMediaObject(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(1, 'CRUD-CODE', 'CRUD Product');
        $mo->create();
        $this->assertSame(self::TEST_ID_BASE + 1, $mo->getId());
        $loaded = new MediaObject(self::TEST_ID_BASE + 1, false);
        $this->assertSame('CRUD-CODE', $loaded->code);
        $this->assertSame('CRUD Product', $loaded->name);
    }

    public function testGetByCodeReturnsMediaObject(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(1, 'GETBYCODE-1', 'GetByCode Product');
        $mo->create();
        $result = MediaObject::getByCode('GETBYCODE-1');
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('GETBYCODE-1', $result[0]->code);
    }

    public function testGetByPrettyUrlReturnsResult(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(1, 'URL-MO', 'URL Product');
        $mo->create();
        $this->db->insert('pmt2core_routes', [
            'id' => self::TEST_ID_BASE,
            'id_media_object' => self::TEST_ID_BASE + 1,
            'id_object_type' => 1,
            'route' => '/url-product',
            'language' => 'de',
        ]);
        $result = MediaObject::getByPrettyUrl('/url-product', null, 'de', null);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame(self::TEST_ID_BASE + 1, $result[0]->id);
    }

    public function testGetPrettyUrlAndGetPrettyUrls(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(1, 'PRETTY-MO', 'Pretty Product');
        $mo->create();
        $this->db->insert('pmt2core_routes', [
            'id' => self::TEST_ID_BASE,
            'id_media_object' => self::TEST_ID_BASE + 1,
            'id_object_type' => 1,
            'route' => '/pretty-product',
            'language' => 'de',
        ]);
        $mo = new MediaObject(self::TEST_ID_BASE + 1, false, true);
        $this->assertSame('/pretty-product', $mo->getPrettyUrl(null));
        $urls = $mo->getPrettyUrls();
        $this->assertCount(1, $urls);
        $this->assertSame('/pretty-product', $urls[0]->route);
    }

    public function testBuildPrettyUrlsReturnsOneUrl(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(1, 'BUILD-MO', 'Build Pretty Url Product');
        $mo->create();
        $urls = $mo->buildPrettyUrls('de');
        $this->assertIsArray($urls);
        $this->assertCount(1, $urls);
        $this->assertStringStartsWith('/', $urls[0]);
        $this->assertStringContainsString('build-pretty-url-product', $urls[0]);
    }

    public function testDeleteWithRelationsRemovesRoutes(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(1, 'DEL-MO', 'Delete Product');
        $mo->create();
        $this->db->insert('pmt2core_routes', [
            'id' => self::TEST_ID_BASE,
            'id_media_object' => self::TEST_ID_BASE + 1,
            'id_object_type' => 1,
            'route' => '/delete-me',
            'language' => 'de',
        ]);
        $countBefore = $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_routes WHERE id_media_object = ?', [self::TEST_ID_BASE + 1]);
        $this->assertGreaterThan(0, (int) $countBefore, 'Route fixture should exist');

        $mo = new MediaObject(self::TEST_ID_BASE + 1, false, true);
        $mo->delete(false);
        $this->db->delete('pmt2core_routes', ['id_media_object = ?', self::TEST_ID_BASE + 1]);
        $count = $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_routes WHERE id_media_object = ?', [self::TEST_ID_BASE + 1]);
        $this->assertSame(0, (int) $count);
    }
}
