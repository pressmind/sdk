<?php

namespace Pressmind\Tests\Integration\ORM;

use Pressmind\CLI\DatabaseIntegrityCheckCommand;
use Pressmind\DB\IntegrityCheck\Mysql as IntegrityCheck;
use Pressmind\DB\Scaffolder\Mysql as Scaffolder;
use Pressmind\ObjectTypeScaffolder;
use Pressmind\ORM\Object\MediaObject\DataType\Icon;
use Pressmind\ORM\Object\MediaObject\DataType\Repeated_form;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

class IconImportIntegrationTest extends AbstractIntegrationTestCase
{
    private const MEDIA_ID = 990042;
    private const CLASS_NAME = '\\Custom\\MediaType\\Sdk_icon_test';
    private string $classFile = '';

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
        foreach ([new Icon(), new Repeated_form(), new Repeated_form\Row(), new Repeated_form\Row\Column()] as $model) {
            (new Scaffolder($model))->run();
        }
        $this->classFile = APPLICATION_PATH . '/Custom/MediaType/Sdk_icon_test.php';
        $scaffolder = new ObjectTypeScaffolder((object) ['name' => 'sdk_icon_test'], 'sdk_icon_test');
        $scaffolder->generateORMFile([
            ['id', 'integer', 'integer'],
            ['id_media_object', 'integer', 'integer'],
            ['language', 'longtext', 'string'],
            ['symbol_default', 'icon', 'relation'],
            ['symbol_sidebar', 'icon', 'relation'],
            ['leistungen_default', 'repeated_form', 'relation'],
        ]);
        (new Scaffolder($this->newMediaType()))->run();
        $this->cleanTestData();
    }

    protected function tearDown(): void
    {
        if ($this->db !== null && $this->classFile !== '') {
            $this->cleanTestData();
            $this->db->execute('DROP TABLE IF EXISTS objectdata_sdk_icon_test');
            $this->db->execute('DROP TABLE IF EXISTS _test_icon_column_migration');
            if (is_file($this->classFile)) {
                unlink($this->classFile);
            }
        }
        parent::tearDown();
    }

    private function newMediaType()
    {
        $class = self::CLASS_NAME;
        return new $class();
    }

    private function cleanTestData(): void
    {
        foreach (['de', 'en'] as $language) {
            $media = $this->newMediaType();
            $media->read(self::MEDIA_ID, $language);
            if ($media->getId()) {
                $media->delete(true);
            }
        }
    }

    private function importValues(string $language, string $iconId): void
    {
        $fixture = json_decode(file_get_contents(__DIR__ . '/../../Fixtures/icons/repeated-form.json'));
        $icon = $fixture->values[0]->values->symbol;
        $icon->id = $iconId;
        $sidebar = clone $icon;
        $sidebar->id .= '-sidebar';
        $media = $this->newMediaType();
        $media->fromImport([
            'id_media_object' => self::MEDIA_ID,
            'language' => $language,
            'symbol_default' => $icon,
            'symbol_sidebar' => $sidebar,
            'leistungen_default' => (array) $fixture,
        ]);
        $media->create();
    }

    public function testScaffoldedIconsRoundTripByLanguageAndSection(): void
    {
        $this->importValues('de', 'ship-de');
        $this->importValues('en', 'ship-en');
        foreach (['de', 'en'] as $language) {
            $media = $this->newMediaType();
            $media->read(self::MEDIA_ID, $language);
            $this->assertCount(1, $media->symbol_default);
            $this->assertSame('ship-' . $language, $media->symbol_default[0]->id_icon);
            $this->assertSame('ship-' . $language . '-sidebar', $media->symbol_sidebar[0]->id_icon);
            $column = $media->leistungen_default[0]->rows[0]->columns[1];
            $this->assertSame('icon', $column->datatype);
            $this->assertSame('ship-' . $language, $column->value_icon['id']);
            foreach (['name', 'slug', 'url', 'mime', 'style', 'variants'] as $property) {
                $this->assertSame($media->symbol_default[0]->$property, $column->value_icon[$property]);
            }
            $this->assertSame('Schiffsreise', $media->leistungen_default[0]->rows[0]->columns[0]->value_string);
            $this->assertSame('2026-06-15', $media->leistungen_default[0]->rows[0]->valid_from->format('Y-m-d'));
            $json = json_decode($media->toJson(), true);
            $this->assertSame($column->value_icon, $json['leistungen_default'][0]['rows'][0]['columns'][1]['value_icon']);
        }
    }

    public function testReimportReplacesAndClearsIconsWithoutDuplicates(): void
    {
        $this->importValues('de', 'old');
        $this->importValues('en', 'english');
        $media = $this->newMediaType();
        $media->read(self::MEDIA_ID, 'de');
        $media->delete(true);
        $this->importValues('de', 'new');
        $media = $this->newMediaType();
        $media->read(self::MEDIA_ID, 'de');
        $this->assertSame('new', $media->symbol_default[0]->id_icon);
        $this->assertCount(1, $media->leistungen_default);
        $this->assertCount(1, $media->leistungen_default[0]->rows);
        $this->assertSame('new', $media->leistungen_default[0]->rows[0]->columns[1]->value_icon['id']);
        $this->assertSame(2, (int) $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_media_object_icons WHERE id_media_object = ? AND language = ?', [self::MEDIA_ID, 'de']));
        $media->delete(true);
        $fixture = json_decode(file_get_contents(__DIR__ . '/../../Fixtures/icons/repeated-form.json'));
        $fixture->values[0]->values->symbol = null;
        $media = $this->newMediaType();
        $media->fromImport(['id_media_object' => self::MEDIA_ID, 'language' => 'de', 'symbol_default' => null, 'symbol_sidebar' => null, 'leistungen_default' => (array) $fixture]);
        $media->create();
        $media = $this->newMediaType();
        $media->read(self::MEDIA_ID, 'de');
        $this->assertEmpty($media->symbol_default);
        $this->assertNull($media->leistungen_default[0]->rows[0]->columns[1]->value_icon);
        $this->assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_media_object_icons WHERE id_media_object = ? AND language = ?', [self::MEDIA_ID, 'de']));
        $english = $this->newMediaType();
        $english->read(self::MEDIA_ID, 'en');
        $this->assertSame('english', $english->symbol_default[0]->id_icon);
        $this->assertSame('english', $english->leistungen_default[0]->rows[0]->columns[1]->value_icon['id']);
    }

    public function testExistingSchemaGainsNullableIconColumnWithoutLosingText(): void
    {
        $column = new class extends Repeated_form\Row\Column {
            public function __construct() {
                $this->_definitions['database']['table_name'] = '_test_icon_column_migration';
                parent::__construct();
            }
        };
        (new Scaffolder($column))->run(true);
        $this->db->execute('ALTER TABLE _test_icon_column_migration DROP COLUMN value_icon');
        $this->db->execute("INSERT INTO _test_icon_column_migration (id_repeated_form, id_repeated_form_row, sort, datatype, value_string) VALUES (1, 1, 1, 'string', 'Existing text')");
        $differences = (new IntegrityCheck($column))->check();
        $missingColumns = array_values(array_filter($differences, static fn($difference) => $difference['action'] === 'create_column'));
        $this->assertCount(1, $missingColumns);
        $this->assertSame('value_icon', $missingColumns[0]['column_name']);
        $this->assertSame('longtext', $missingColumns[0]['column_type']);
        $method = new \ReflectionMethod(DatabaseIntegrityCheckCommand::class, 'applyStaticModelDifferences');
        $method->setAccessible(true);
        ob_start();
        try {
            $method->invoke(new DatabaseIntegrityCheckCommand(), $column, get_class($column), $differences);
        } finally {
            ob_end_clean();
        }
        $this->assertSame(true, (new IntegrityCheck($column))->check());
        $stored = $this->db->fetchRow('SELECT value_string, value_icon FROM _test_icon_column_migration');
        $this->assertSame('Existing text', $stored->value_string);
        $this->assertNull($stored->value_icon);
    }
}
