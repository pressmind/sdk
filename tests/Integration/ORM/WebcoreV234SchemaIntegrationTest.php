<?php

namespace Pressmind\Tests\Integration\ORM;

use Custom\MediaType\Pauschalreise;
use Pressmind\CLI\DatabaseIntegrityCheckCommand;
use Pressmind\DB\IntegrityCheck\Mysql as IntegrityCheck;
use Pressmind\DB\Scaffolder\Mysql as Scaffolder;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\MediaObject\DataType\Picture;
use Pressmind\Registry;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

class WebcoreV234SchemaIntegrationTest extends AbstractIntegrationTestCase
{
    private const PICTURE_TABLE = '_test_v234_picture_video';
    private const MEDIA_OBJECT_TABLE = '_test_v234_media_object_section_control';
    private const SECTION_CONTROL_IDS = [990235, 990236];

    /** @var string[] tables created by this test that did not exist before */
    private array $createdTables = [];

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
        // Picture::create() checks the video storage, so file_handling must be configured (empty bucket, no file exists)
        $config = Registry::getInstance()->get('config');
        $config['file_handling'] = [
            'storage' => [
                'provider' => 'filesystem',
                'bucket' => sys_get_temp_dir() . '/pm_v234_schema_' . getmypid(),
            ],
            'http_src' => 'http://localhost/assets/files',
        ];
        Registry::getInstance()->add('config', $config);
        // Picture::create() also writes derivative and section rows
        foreach ([new Picture(), new Picture\Derivative(), new Picture\Section()] as $object) {
            (new Scaffolder($object))->run();
        }
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            $this->db->execute('DROP TABLE IF EXISTS ' . self::PICTURE_TABLE);
            $this->db->execute('DROP TABLE IF EXISTS ' . self::MEDIA_OBJECT_TABLE);
            $this->db->delete('pmt2core_media_object_images', ['var_name = ?', 'v234_roundtrip']);
            $ids = implode(',', self::SECTION_CONTROL_IDS);
            foreach (['pmt2core_routes' => 'id_media_object', 'pmt2core_media_objects' => 'id'] as $table => $column) {
                if (!in_array($table, $this->createdTables, true) && $this->tableExists($table)) {
                    $this->db->execute('DELETE FROM ' . $table . ' WHERE ' . $column . ' IN (' . $ids . ')');
                }
            }
            foreach ($this->createdTables as $table) {
                $this->db->execute('DROP TABLE IF EXISTS `' . $table . '`');
            }
            $this->createdTables = [];
        }
        parent::tearDown();
    }

    private function tableExists(string $table): bool
    {
        return $this->db->fetchOne('SHOW TABLES LIKE ?', [$table]) !== null;
    }

    /**
     * MediaObject::create() lazy-loads its relations, so the media object table and all
     * directly related tables must exist. Missing ones are created and dropped again in tearDown().
     */
    private function ensureMediaObjectTables(): void
    {
        $classes = [MediaObject::class, Pauschalreise::class];
        foreach ((new MediaObject())->getPropertyDefinitions() as $property) {
            if ($property['type'] === 'relation' && isset($property['relation']['class'])) {
                $classes[] = $property['relation']['class'];
                if (isset($property['relation']['relation_class'])) {
                    $classes[] = $property['relation']['relation_class'];
                }
            }
        }
        foreach (array_unique($classes) as $class) {
            $reflection = new \ReflectionClass($class);
            if ($reflection->isAbstract() || !$reflection->isSubclassOf(AbstractObject::class)) {
                continue;
            }
            $object = new $class();
            $table = $object->getDbTableName();
            if (empty($table) || $this->tableExists($table)) {
                continue;
            }
            (new Scaffolder($object))->run();
            $this->createdTables[] = $table;
        }
    }

    private function createSectionControlMediaObject(int $id, string $code, string $name): MediaObject
    {
        $mo = new MediaObject(null, false);
        $mo->id = $id;
        $mo->id_pool = 1;
        $mo->id_object_type = 1;
        $mo->id_client = 1;
        // ids without brand/season rows, so create() does not re-insert related objects
        $mo->id_brand = $id;
        $mo->id_season = $id;
        $mo->name = $name;
        $mo->code = $code;
        $mo->visibility = 30;
        $mo->state = 50;
        $mo->hidden = 0;
        return $mo;
    }

    private function applyStaticDifferences(AbstractObject $model, array $differences): void
    {
        $method = new \ReflectionMethod(DatabaseIntegrityCheckCommand::class, 'applyStaticModelDifferences');
        $method->setAccessible(true);
        ob_start();
        try {
            $method->invoke(new DatabaseIntegrityCheckCommand(), $model, get_class($model), $differences);
        } finally {
            ob_end_clean();
        }
    }

    private function createdColumns(array $differences): array
    {
        $columns = array_values(array_filter($differences, static fn($difference) => $difference['action'] === 'create_column'));
        return array_column($columns, 'column_name');
    }

    public function testPictureVideoRoundTrip(): void
    {
        $picture = new Picture();
        $picture->id_picture = 4052278;
        $picture->id_media_object = 42;
        $picture->section_name = '';
        $picture->language = 'de';
        $picture->var_name = 'v234_roundtrip';
        $picture->file_name = '42_4052278.jpg';
        $picture->video_tmp_url = 'https://image-archive.pressmind.io/clip/video.mp4';
        $picture->video_file_name = '42_4052278_' . md5('https://image-archive.pressmind.io/clip/video.mp4') . '.mp4';
        $picture->video_mime_type = 'video/mp4';
        $picture->video_width = 1280;
        $picture->video_height = 720;
        $picture->video_duration = 6.04;
        $picture->video_file_size = 4718592;
        $picture->video_download_successful = false;
        $picture->create();

        $plain = new Picture();
        $plain->id_picture = 4052279;
        $plain->id_media_object = 42;
        $plain->section_name = '';
        $plain->language = 'de';
        $plain->var_name = 'v234_roundtrip';
        $plain->file_name = '42_4052279.jpg';
        $plain->create();

        $loaded = new Picture();
        $loaded->read($picture->id);
        $this->assertSame('https://image-archive.pressmind.io/clip/video.mp4', $loaded->video_tmp_url);
        $this->assertSame($picture->video_file_name, $loaded->video_file_name);
        $this->assertSame('video/mp4', $loaded->video_mime_type);
        $this->assertSame(1280, (int) $loaded->video_width);
        $this->assertSame(720, (int) $loaded->video_height);
        $this->assertEqualsWithDelta(6.04, (float) $loaded->video_duration, 0.001);
        $this->assertSame(4718592, (int) $loaded->video_file_size);
        $this->assertFalse((bool) $loaded->video_download_successful);

        $loadedPlain = new Picture();
        $loadedPlain->read($plain->id);
        $this->assertNull($loadedPlain->video_tmp_url);
        $this->assertNull($loadedPlain->video_file_name);
        $this->assertFalse($loadedPlain->hasVideo());
    }

    public function testExistingPictureTableGainsNullableVideoColumns(): void
    {
        $model = new class extends Picture {
            public function __construct() {
                $this->_definitions['database']['table_name'] = '_test_v234_picture_video';
                parent::__construct();
            }
        };
        (new Scaffolder($model))->run(true);
        $videoColumns = ['video_tmp_url', 'video_file_name', 'video_mime_type', 'video_width', 'video_height', 'video_duration', 'video_file_size', 'video_download_successful'];
        foreach ($videoColumns as $column) {
            $this->db->execute('ALTER TABLE ' . self::PICTURE_TABLE . ' DROP COLUMN ' . $column);
        }
        $this->db->execute('INSERT INTO ' . self::PICTURE_TABLE . " (id_picture, id_media_object, var_name, file_name) VALUES (1, 42, 'gallery', 'existing.jpg')");

        $differences = (new IntegrityCheck($model))->check();
        $created = $this->createdColumns($differences);
        sort($created);
        $expected = $videoColumns;
        sort($expected);
        $this->assertSame($expected, $created);

        $this->applyStaticDifferences($model, $differences);

        $this->assertSame(true, (new IntegrityCheck($model))->check());
        $stored = $this->db->fetchRow('SELECT file_name, video_tmp_url, video_file_name FROM ' . self::PICTURE_TABLE);
        $this->assertSame('existing.jpg', $stored->file_name);
        $this->assertNull($stored->video_tmp_url);
        $this->assertNull($stored->video_file_name);
    }

    public function testExistingMediaObjectTableGainsNullableSectionControlColumn(): void
    {
        $model = new class extends MediaObject {
            public function __construct() {
                $this->_definitions['database']['table_name'] = '_test_v234_media_object_section_control';
                parent::__construct();
            }
        };
        (new Scaffolder($model))->run(true);
        $this->db->execute('ALTER TABLE ' . self::MEDIA_OBJECT_TABLE . ' DROP COLUMN section_control');
        $this->db->execute('INSERT INTO ' . self::MEDIA_OBJECT_TABLE . " (id, id_pool, id_object_type, id_client, id_brand, id_season, name, visibility, state, hidden) VALUES (990234, 1, 1, 1, 1, 1, 'Existing', 30, 50, 0)");

        $differences = (new IntegrityCheck($model))->check();
        $this->assertSame(['section_control'], $this->createdColumns($differences));

        $this->applyStaticDifferences($model, $differences);

        $this->assertSame(true, (new IntegrityCheck($model))->check());
        $stored = $this->db->fetchRow('SELECT name, section_control FROM ' . self::MEDIA_OBJECT_TABLE . ' WHERE id = 990234');
        $this->assertSame('Existing', $stored->name);
        $this->assertNull($stored->section_control);
    }

    public function testMediaObjectSectionControlRoundTrip(): void
    {
        // MediaObject::create() resolves the media type and pretty urls from config (same as MediaObjectIntegrationTest)
        $config = Registry::getInstance()->get('config');
        $config['data']['languages'] = ['default' => 'de', 'allowed' => ['de', 'en']];
        $config['data']['media_types'] = [1 => 'pauschalreise'];
        $config['data']['media_types_pretty_url'] = [
            1 => ['fields' => ['name'], 'separator' => '-', 'strategy' => 'unique', 'prefix' => '/', 'suffix' => ''],
        ];
        Registry::getInstance()->add('config', $config);
        $this->ensureMediaObjectTables();
        [$controlledId, $plainId] = self::SECTION_CONTROL_IDS;
        $mo = $this->createSectionControlMediaObject($controlledId, 'SC-CODE', 'Section Control Product');
        $mo->section_control = ['beschreibung' => ['possibly_outdated' => true]];
        $mo->create();
        $plain = $this->createSectionControlMediaObject($plainId, 'SC-PLAIN', 'Plain Product');
        $plain->create();

        $loaded = new MediaObject($controlledId, false);
        $this->assertSame(['beschreibung' => ['possibly_outdated' => true]], $loaded->section_control);
        $this->assertTrue($loaded->isPossiblyOutdated('beschreibung'));

        $loadedPlain = new MediaObject($plainId, false);
        $this->assertNull($loadedPlain->section_control);
        $this->assertFalse($loadedPlain->isSectionControlled('beschreibung'));
    }
}
