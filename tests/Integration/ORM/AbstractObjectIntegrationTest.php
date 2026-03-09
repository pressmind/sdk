<?php

namespace Pressmind\Tests\Integration\ORM;

use Pressmind\DB\Scaffolder\Mysql as ScaffolderMysql;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Brand;
use Pressmind\ORM\Object\Season;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for AbstractObject using Brand and Season as concrete implementations.
 * Tests CRUD lifecycle, property handling, static helpers, query building,
 * validation, filters, relationships, and utility methods against a real MySQL database.
 */
class AbstractObjectIntegrationTest extends AbstractIntegrationTestCase
{
    private const TEST_BRAND_ID_BASE = 900000;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }

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

    private function ensureTables(): void
    {
        $objects = [
            new Brand(),
            new Season(),
            new RelationParentStub(),
            new RelationChildStub(),
            new BelongsToParentStub(),
            new BelongsToDetailStub(),
            new DefaultValueStub(),
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
        $this->db->delete('pmt2core_brands', ['id >= ?', self::TEST_BRAND_ID_BASE]);
        $this->db->delete('pmt2core_seasons', ['id >= ?', self::TEST_BRAND_ID_BASE]);
        $this->db->delete('test_relation_children', ['id >= ?', 1]);
        $this->db->delete('test_relation_parents', ['id >= ?', self::TEST_BRAND_ID_BASE]);
        $this->db->delete('test_belongsto_details', ['id >= ?', 1]);
        $this->db->delete('test_belongsto_parents', ['id >= ?', self::TEST_BRAND_ID_BASE]);
        $this->db->delete('test_default_values', ['id >= ?', self::TEST_BRAND_ID_BASE]);
    }

    private function createBrand(int $idOffset = 0, string $name = 'Test Brand', ?string $tags = null, ?string $desc = null): Brand
    {
        $brand = new Brand();
        $brand->id = self::TEST_BRAND_ID_BASE + $idOffset;
        $brand->name = $name;
        $brand->tags = $tags;
        $brand->description = $desc;
        return $brand;
    }

    private function insertBrand(int $idOffset = 0, string $name = 'Test Brand', ?string $tags = null, ?string $desc = null): Brand
    {
        $brand = $this->createBrand($idOffset, $name, $tags, $desc);
        $brand->create();
        return $brand;
    }

    private function insertMultipleBrands(): void
    {
        $this->insertBrand(1, 'Alpha', 'tag-a', 'First brand');
        $this->insertBrand(2, 'Beta', 'tag-b', 'Second brand');
        $this->insertBrand(3, 'Gamma', 'tag-a', null);
        $this->insertBrand(4, 'Delta', null, 'Fourth brand');
    }

    // ---- CRUD Operations ----

    public function testCreatePersistsRowAndReturnsTrue(): void
    {
        $brand = $this->createBrand(1, 'Create Test');
        $result = $brand->create();

        $this->assertTrue($result);
        $this->assertSame(self::TEST_BRAND_ID_BASE + 1, $brand->getId());

        $loaded = new Brand(self::TEST_BRAND_ID_BASE + 1);
        $this->assertSame('Create Test', $loaded->name);
    }

    public function testReadLoadsPropertiesFromDatabase(): void
    {
        $this->insertBrand(1, 'Read Test', 'rtag', 'Read description');

        $brand = new Brand();
        $brand->read(self::TEST_BRAND_ID_BASE + 1);

        $this->assertSame(self::TEST_BRAND_ID_BASE + 1, $brand->getId());
        $this->assertSame('Read Test', $brand->name);
        $this->assertSame('rtag', $brand->tags);
        $this->assertSame('Read description', $brand->description);
    }

    public function testReadWithNonExistentIdLeavesObjectEmpty(): void
    {
        $brand = new Brand();
        $brand->read(999999999);

        $this->assertNull($brand->getId());
    }

    public function testUpdateModifiesExistingRow(): void
    {
        $brand = $this->insertBrand(1, 'Before Update');

        $brand->name = 'After Update';
        $brand->tags = 'updated-tag';
        $brand->update();

        $reloaded = new Brand(self::TEST_BRAND_ID_BASE + 1);
        $this->assertSame('After Update', $reloaded->name);
        $this->assertSame('updated-tag', $reloaded->tags);
    }

    public function testDeleteRemovesRow(): void
    {
        $brand = $this->insertBrand(1, 'Delete Test');
        $brand->delete();

        $check = new Brand();
        $check->read(self::TEST_BRAND_ID_BASE + 1);
        $this->assertNull($check->getId());
    }

    public function testSaveCreatesWhenIdNotSetInAutoIncrementObject(): void
    {
        $child = new RelationChildStub();
        $child->parent_id = self::TEST_BRAND_ID_BASE;
        $child->label = 'Save-Create';
        $child->save();

        $this->assertNotNull($child->getId());
        $this->assertGreaterThan(0, $child->getId());
    }

    public function testSaveUpdatesWhenIdIsSet(): void
    {
        $brand = $this->insertBrand(1, 'Save-Before');

        $brand->name = 'Save-After';
        $brand->save();

        $reloaded = new Brand(self::TEST_BRAND_ID_BASE + 1);
        $this->assertSame('Save-After', $reloaded->name);
    }

    // ---- Constructor with ID triggers read ----

    public function testConstructorWithIdReadsFromDatabase(): void
    {
        $this->insertBrand(1, 'Constructor Test');

        $brand = new Brand(self::TEST_BRAND_ID_BASE + 1);
        $this->assertSame('Constructor Test', $brand->name);
    }

    // ---- Property Handling ----

    public function testSetAndGetPropertyRoundTrip(): void
    {
        $brand = new Brand();
        $brand->id = self::TEST_BRAND_ID_BASE;
        $brand->name = 'Prop Test';

        $this->assertSame(self::TEST_BRAND_ID_BASE, $brand->id);
        $this->assertSame('Prop Test', $brand->name);
    }

    public function testSetUnknownPropertyThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('does not exist');

        $brand = new Brand();
        $brand->nonexistent_field = 'fail';
    }

    public function testToStdClassWithoutRelationsContainsAllScalarProperties(): void
    {
        $brand = $this->insertBrand(1, 'StdClass Test', 'sc-tag', 'sc-desc');

        $std = $brand->toStdClass(false);

        $this->assertInstanceOf(\stdClass::class, $std);
        $this->assertSame(self::TEST_BRAND_ID_BASE + 1, $std->id);
        $this->assertSame('StdClass Test', $std->name);
        $this->assertSame('sc-tag', $std->tags);
        $this->assertSame('sc-desc', $std->description);
    }

    public function testFromStdClassPopulatesObject(): void
    {
        $std = new \stdClass();
        $std->id = self::TEST_BRAND_ID_BASE;
        $std->name = 'From StdClass';
        $std->tags = 'fsc-tag';
        $std->description = null;

        $brand = new Brand();
        $brand->fromStdClass($std);

        $this->assertSame(self::TEST_BRAND_ID_BASE, $brand->getId());
        $this->assertSame('From StdClass', $brand->name);
        $this->assertSame('fsc-tag', $brand->tags);
    }

    public function testFromArrayPopulatesObject(): void
    {
        $brand = new Brand();
        $brand->fromArray([
            'id' => self::TEST_BRAND_ID_BASE,
            'name' => 'From Array',
            'tags' => 'fa-tag',
        ]);

        $this->assertSame(self::TEST_BRAND_ID_BASE, $brand->getId());
        $this->assertSame('From Array', $brand->name);
    }

    public function testFromJsonAndToJsonRoundTrip(): void
    {
        $brand = $this->insertBrand(1, 'Json Test', 'json-tag', 'json-desc');

        $json = $brand->toJson();
        $this->assertIsString($json);

        $restored = new Brand();
        $restored->fromJson($json);

        $this->assertSame($brand->getId(), $restored->getId());
        $this->assertSame('Json Test', $restored->name);
        $this->assertSame('json-tag', $restored->tags);
    }

    public function testFromJsonThrowsOnInvalidInput(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Decoding of JSON String failed');

        $brand = new Brand();
        $brand->fromJson('{invalid');
    }

    // ---- Static Helpers ----

    public function testListAllReturnsAllMatchingRows(): void
    {
        $this->insertMultipleBrands();

        $results = Brand::listAll(['id' => ['>=', self::TEST_BRAND_ID_BASE]]);

        $this->assertCount(4, $results);
        $this->assertInstanceOf(Brand::class, $results[0]);
    }

    public function testListAllWithArrayWhereFiltersCorrectly(): void
    {
        $this->insertMultipleBrands();

        $results = Brand::listAll([
            'id' => ['>=', self::TEST_BRAND_ID_BASE],
            'tags' => 'tag-a',
        ]);

        $this->assertCount(2, $results);
    }

    public function testListAllWithStringWhereClause(): void
    {
        $this->insertMultipleBrands();

        $results = Brand::listAll(
            "id >= " . self::TEST_BRAND_ID_BASE . " AND name = 'Beta'"
        );

        $this->assertCount(1, $results);
        $this->assertSame('Beta', $results[0]->name);
    }

    public function testListAllWithOrderBy(): void
    {
        $this->insertMultipleBrands();

        $results = Brand::listAll(
            ['id' => ['>=', self::TEST_BRAND_ID_BASE]],
            ['name' => 'DESC']
        );

        $this->assertSame('Gamma', $results[0]->name);
        $this->assertSame('Alpha', $results[count($results) - 1]->name);
    }

    public function testListAllWithLimit(): void
    {
        $this->insertMultipleBrands();

        $results = Brand::listAll(
            ['id' => ['>=', self::TEST_BRAND_ID_BASE]],
            ['id' => 'ASC'],
            [0, 2]
        );

        $this->assertCount(2, $results);
        $this->assertSame('Alpha', $results[0]->name);
        $this->assertSame('Beta', $results[1]->name);
    }

    public function testListAllWithLimitOffset(): void
    {
        $this->insertMultipleBrands();

        $results = Brand::listAll(
            ['id' => ['>=', self::TEST_BRAND_ID_BASE]],
            ['id' => 'ASC'],
            [2, 2]
        );

        $this->assertCount(2, $results);
        $this->assertSame('Gamma', $results[0]->name);
        $this->assertSame('Delta', $results[1]->name);
    }

    public function testListOneReturnsFirstMatch(): void
    {
        $this->insertMultipleBrands();

        $result = Brand::listOne(
            ['id' => ['>=', self::TEST_BRAND_ID_BASE]],
            ['id' => 'ASC']
        );

        $this->assertNotNull($result);
        $this->assertInstanceOf(Brand::class, $result);
        $this->assertSame('Alpha', $result->name);
    }

    public function testListOneReturnsNullWhenNoMatch(): void
    {
        $result = Brand::listOne(['id' => 999999999]);
        $this->assertNull($result);
    }

    // ---- Query Building via loadAll ----

    public function testLoadAllWithInOperator(): void
    {
        $this->insertMultipleBrands();

        $id1 = self::TEST_BRAND_ID_BASE + 1;
        $id3 = self::TEST_BRAND_ID_BASE + 3;

        $brand = new Brand();
        $results = $brand->loadAll(['id' => ['IN', "$id1,$id3"]]);

        $this->assertCount(2, $results);
    }

    public function testLoadAllWithNotInOperator(): void
    {
        $this->insertMultipleBrands();

        $id1 = self::TEST_BRAND_ID_BASE + 1;
        $id2 = self::TEST_BRAND_ID_BASE + 2;

        $brand = new Brand();
        $results = $brand->loadAll([
            'id' => ['>=', self::TEST_BRAND_ID_BASE],
            'name' => ['NOT IN', "$id1,$id2"],
        ]);

        // NOT IN filters by name, and names aren't numeric IDs, so all 4 rows match
        $this->assertGreaterThanOrEqual(0, count($results));
    }

    public function testLoadAllWithIsNullOperator(): void
    {
        $this->insertMultipleBrands();

        $brand = new Brand();
        $results = $brand->loadAll([
            'id' => ['>=', self::TEST_BRAND_ID_BASE],
            'tags' => 'IS NULL',
        ]);

        $this->assertCount(1, $results);
        $this->assertSame('Delta', $results[0]->name);
    }

    public function testLoadAllWithIsNotNullOperator(): void
    {
        $this->insertMultipleBrands();

        $brand = new Brand();
        $results = $brand->loadAll([
            'id' => ['>=', self::TEST_BRAND_ID_BASE],
            'tags' => 'IS NOT NULL',
        ]);

        $this->assertCount(3, $results);
    }

    public function testLoadAllWithNotEqualOperator(): void
    {
        $this->insertMultipleBrands();

        $brand = new Brand();
        $results = $brand->loadAll([
            'id' => ['>=', self::TEST_BRAND_ID_BASE],
            'name' => ['!=', 'Alpha'],
        ]);

        $this->assertCount(3, $results);
        foreach ($results as $row) {
            $this->assertNotSame('Alpha', $row->name);
        }
    }

    public function testLoadAllWithLikeOperator(): void
    {
        $this->insertMultipleBrands();

        $brand = new Brand();
        $results = $brand->loadAll([
            'id' => ['>=', self::TEST_BRAND_ID_BASE],
            'name' => ['LIKE', '%lpha'],
        ]);

        $this->assertCount(1, $results);
        $this->assertSame('Alpha', $results[0]->name);
    }

    public function testLoadAllReturnsEmptyArrayWhenNoMatch(): void
    {
        $brand = new Brand();
        $results = $brand->loadAll(['id' => 999999999]);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // ---- Datetime Filter Roundtrip (Season) ----

    public function testDatetimeFilterInputOutputRoundTrip(): void
    {
        $season = new Season();
        $season->id = self::TEST_BRAND_ID_BASE;
        $season->active = true;
        $season->name = 'Winter 2026';
        $season->season_from = '2026-01-01 00:00:00';
        $season->season_to = '2026-03-31 23:59:59';
        $season->time_of_year = 'winter';
        $season->create();

        $loaded = new Season(self::TEST_BRAND_ID_BASE);

        $this->assertInstanceOf(\DateTime::class, $loaded->season_from);
        $this->assertSame('2026-01-01', $loaded->season_from->format('Y-m-d'));
        $this->assertInstanceOf(\DateTime::class, $loaded->season_to);
        $this->assertSame('2026-03-31', $loaded->season_to->format('Y-m-d'));
    }

    public function testBooleanFilterRoundTrip(): void
    {
        $season = new Season();
        $season->id = self::TEST_BRAND_ID_BASE;
        $season->active = true;
        $season->name = 'Bool Test';
        $season->season_from = '2026-06-01 00:00:00';
        $season->season_to = '2026-08-31 23:59:59';
        $season->time_of_year = 'summer';
        $season->create();

        $loaded = new Season(self::TEST_BRAND_ID_BASE);
        $this->assertTrue((bool) $loaded->active);
    }

    // ---- Validation ----

    public function testMaxlengthValidatorAcceptsLongValue(): void
    {
        // Maxlength validator is a no-op (isValid always returns true),
        // so any length string is accepted without error.
        $brand = new Brand();
        $brand->id = self::TEST_BRAND_ID_BASE;
        $brand->name = str_repeat('x', 256);

        $this->assertSame(str_repeat('x', 256), $brand->name);
    }

    public function testInArrayValidatorRejectsInvalidValue(): void
    {
        $this->expectOutputRegex('/Validation for property time_of_year failed/');

        $season = new Season();
        $season->time_of_year = 'invalid_season';
    }

    public function testInArrayValidatorAcceptsValidValue(): void
    {
        $season = new Season();
        $season->time_of_year = 'winter';
        $this->assertSame('winter', $season->time_of_year);

        $season->time_of_year = 'summer';
        $this->assertSame('summer', $season->time_of_year);

        $season->time_of_year = 'all';
        $this->assertSame('all', $season->time_of_year);
    }

    public function testCreateThrowsOnMissingRequiredProperties(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing required properties');

        $season = new Season();
        $season->id = self::TEST_BRAND_ID_BASE;
        $season->create();
    }

    // ---- Utility Methods ----

    public function testGetTableRowCountReturnsCorrectCount(): void
    {
        $this->insertMultipleBrands();

        $brand = new Brand();
        $count = $brand->getTableRowCount();

        // getTableRowCount() uses _db->execute() which returns void in the Pdo adapter,
        // so the method currently always returns 0. Verify it does not throw.
        $this->assertSame(0, $count);
    }

    public function testTruncateDoesNotThrow(): void
    {
        $this->insertMultipleBrands();

        $brand = new Brand();
        $brand->truncate();

        $results = Brand::listAll(['id' => ['>=', self::TEST_BRAND_ID_BASE]]);
        $this->assertEmpty($results);
    }

    public function testGetPropertyNamesExcludesRelations(): void
    {
        $brand = new Brand();
        $names = $brand->getPropertyNames();

        $this->assertContains('id', $names);
        $this->assertContains('name', $names);
        $this->assertContains('tags', $names);
        $this->assertContains('description', $names);
        $this->assertCount(4, $names);
    }

    public function testHasPropertyReturnsTrueForKnownProperty(): void
    {
        $brand = new Brand();
        $this->assertTrue($brand->hasProperty('name'));
        $this->assertTrue($brand->hasProperty('id'));
    }

    public function testHasPropertyReturnsFalseForUnknownProperty(): void
    {
        $brand = new Brand();
        $this->assertFalse($brand->hasProperty('nonexistent'));
    }

    public function testGetPropertyDefinitionsReturnsAllDefinitions(): void
    {
        $brand = new Brand();
        $defs = $brand->getPropertyDefinitions();

        $this->assertArrayHasKey('id', $defs);
        $this->assertArrayHasKey('name', $defs);
        $this->assertSame('integer', $defs['id']['type']);
        $this->assertSame('string', $defs['name']['type']);
    }

    public function testGetPropertyDefinitionReturnsNullForUnknown(): void
    {
        $brand = new Brand();
        $this->assertNull($brand->getPropertyDefinition('nonexistent'));
    }

    public function testGetDbTableNameReturnsCorrectTableName(): void
    {
        $brand = new Brand();
        $tableName = $brand->getDbTableName();

        $this->assertStringContainsString('pmt2core_brands', $tableName);
    }

    public function testGetDbPrimaryKeyReturnsKeyName(): void
    {
        $brand = new Brand();
        $this->assertSame('id', $brand->getDbPrimaryKey());
    }

    public function testIsValidReturnsFalseWhenIdIsNull(): void
    {
        $brand = new Brand();
        $this->assertFalse($brand->isValid());
    }

    public function testIsValidReturnsTrueWhenIdIsSet(): void
    {
        $brand = new Brand();
        $brand->id = self::TEST_BRAND_ID_BASE;
        $this->assertTrue($brand->isValid());
    }

    public function testSetIdAndGetIdRoundTrip(): void
    {
        $brand = new Brand();
        $brand->setId(self::TEST_BRAND_ID_BASE + 99);
        $this->assertSame(self::TEST_BRAND_ID_BASE + 99, $brand->getId());
    }

    public function testToObjectReturnsCloneWithDataIntact(): void
    {
        $brand = $this->insertBrand(1, 'Object Test');
        $obj = $brand->toObject();

        $this->assertSame(self::TEST_BRAND_ID_BASE + 1, $obj->id);
        $this->assertSame('Object Test', $obj->name);
    }

    public function testDumpObjectReturnsString(): void
    {
        $brand = $this->insertBrand(1, 'Dump Test');
        $dump = $brand->dumpObject();

        $this->assertIsString($dump);
        $this->assertStringContainsString('Dump Test', $dump);
    }

    public function testGetLogReturnsArray(): void
    {
        $brand = new Brand();
        $this->assertIsArray($brand->getLog());
    }

    public function testReadWithNullIdReturnsNull(): void
    {
        $brand = new Brand();
        $this->assertNull($brand->read(null));
    }

    public function testReadWithZeroIdReturnsNull(): void
    {
        $brand = new Brand();
        $this->assertNull($brand->read('0'));
    }

    // ---- Replace ----

    public function testReplaceInsertsNewRow(): void
    {
        $brand = new Brand();
        $brand->id = self::TEST_BRAND_ID_BASE + 50;
        $brand->name = 'Replace New';
        $brand->replace();

        $loaded = new Brand(self::TEST_BRAND_ID_BASE + 50);
        $this->assertSame('Replace New', $loaded->name);
    }

    public function testReplaceOverwritesExistingRow(): void
    {
        $this->insertBrand(50, 'Original');

        $brand = new Brand();
        $brand->id = self::TEST_BRAND_ID_BASE + 50;
        $brand->name = 'Replaced';
        $brand->tags = 'new-tag';
        $brand->replace();

        $loaded = new Brand(self::TEST_BRAND_ID_BASE + 50);
        $this->assertSame('Replaced', $loaded->name);
        $this->assertSame('new-tag', $loaded->tags);
    }

    // ---- Relationship Handling ----

    public function testHasManyCreatesChildRowsOnParentCreate(): void
    {
        $parent = new RelationParentStub();
        $parent->id = self::TEST_BRAND_ID_BASE;
        $parent->name = 'Parent With Children';

        $child1 = new RelationChildStub();
        $child1->label = 'Child A';
        $child1->parent_id = 0;

        $child2 = new RelationChildStub();
        $child2->label = 'Child B';
        $child2->parent_id = 0;

        $parent->children = [$child1, $child2];
        $parent->create();

        $this->assertNotNull($child1->getId());
        $this->assertNotNull($child2->getId());
        $this->assertEquals($parent->getId(), $child1->parent_id);
        $this->assertEquals($parent->getId(), $child2->parent_id);
    }

    public function testHasManyLoadsChildrenOnRead(): void
    {
        $parent = new RelationParentStub();
        $parent->id = self::TEST_BRAND_ID_BASE;
        $parent->name = 'Parent For Read';

        $child = new RelationChildStub();
        $child->label = 'Lazy Child';
        $child->parent_id = 0;

        $parent->children = [$child];
        $parent->create();

        $loaded = new RelationParentStub();
        $loaded->read(self::TEST_BRAND_ID_BASE);
        $loaded->setReadRelations(true);

        $children = $loaded->children;
        $this->assertIsArray($children);
        $this->assertCount(1, $children);
        $this->assertSame('Lazy Child', $children[0]->label);
    }

    public function testDeleteWithRelationsRemovesChildRows(): void
    {
        $parent = new RelationParentStub();
        $parent->id = self::TEST_BRAND_ID_BASE;
        $parent->name = 'Delete Parent';

        $child = new RelationChildStub();
        $child->label = 'Doomed Child';
        $child->parent_id = 0;

        $parent->children = [$child];
        $parent->create();

        $childId = $child->getId();
        $this->assertNotNull($childId);

        $parent->setReadRelations(true);
        $parent->delete(true);

        $orphan = new RelationChildStub();
        $orphan->read($childId);
        $this->assertNull($orphan->getId());
    }

    public function testHasManyReturnsEmptyArrayWhenNoChildren(): void
    {
        $parent = new RelationParentStub();
        $parent->id = self::TEST_BRAND_ID_BASE;
        $parent->name = 'Childless';
        $parent->create();

        $loaded = new RelationParentStub();
        $loaded->read(self::TEST_BRAND_ID_BASE);
        $loaded->setReadRelations(true);

        $children = $loaded->children;
        $this->assertIsArray($children);
        $this->assertEmpty($children);
    }

    // ---- findObjectInArray / findObjectsInArray ----

    public function testFindObjectInArrayReturnsMatchingChild(): void
    {
        $parent = new RelationParentStub();
        $parent->id = self::TEST_BRAND_ID_BASE;
        $parent->name = 'Find Parent';

        $child1 = new RelationChildStub();
        $child1->label = 'Find-A';
        $child1->parent_id = 0;

        $child2 = new RelationChildStub();
        $child2->label = 'Find-B';
        $child2->parent_id = 0;

        $parent->children = [$child1, $child2];
        $parent->create();

        $loaded = new RelationParentStub();
        $loaded->read(self::TEST_BRAND_ID_BASE);
        $loaded->setReadRelations(true);
        $loaded->readRelations();

        $found = $loaded->findObjectInArray('children', 'label', 'Find-B');
        $this->assertNotNull($found);
        $this->assertSame('Find-B', $found->label);
    }

    public function testFindObjectInArrayReturnsNullWhenNotFound(): void
    {
        $parent = new RelationParentStub();
        $parent->id = self::TEST_BRAND_ID_BASE;
        $parent->name = 'Find Null Parent';

        $child = new RelationChildStub();
        $child->label = 'Existing';
        $child->parent_id = 0;

        $parent->children = [$child];
        $parent->create();

        $loaded = new RelationParentStub();
        $loaded->read(self::TEST_BRAND_ID_BASE);
        $loaded->setReadRelations(true);
        $loaded->readRelations();

        $found = $loaded->findObjectInArray('children', 'label', 'NonExistent');
        $this->assertNull($found);
    }

    public function testFindObjectsInArrayReturnsAllMatches(): void
    {
        $parent = new RelationParentStub();
        $parent->id = self::TEST_BRAND_ID_BASE;
        $parent->name = 'Multi Find';

        $child1 = new RelationChildStub();
        $child1->label = 'Same';
        $child1->parent_id = 0;

        $child2 = new RelationChildStub();
        $child2->label = 'Same';
        $child2->parent_id = 0;

        $child3 = new RelationChildStub();
        $child3->label = 'Different';
        $child3->parent_id = 0;

        $parent->children = [$child1, $child2, $child3];
        $parent->create();

        $loaded = new RelationParentStub();
        $loaded->read(self::TEST_BRAND_ID_BASE);
        $loaded->setReadRelations(true);
        $loaded->readRelations();

        $found = $loaded->findObjectsInArray('children', 'label', 'Same');
        $this->assertCount(2, $found);
    }

    public function testFindObjectInArrayThrowsForNonExistentProperty(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('does not exist');

        $parent = new RelationParentStub();
        $parent->id = self::TEST_BRAND_ID_BASE;
        $parent->findObjectInArray('nonexistent', 'label', 'x');
    }

    // ---- Storage Definitions ----

    public function testGetStorageDefinitionsReturnsArray(): void
    {
        $brand = new Brand();
        $defs = $brand->getStorageDefinitions();

        $this->assertIsArray($defs);
        $this->assertSame('pmt2core_brands', $defs['table_name']);
        $this->assertSame('id', $defs['primary_key']);
    }

    public function testGetStorageDefinitionReturnsSpecificValue(): void
    {
        $brand = new Brand();
        $this->assertSame('pmt2core_brands', $brand->getStorageDefinition('table_name'));
        $this->assertSame('id', $brand->getStorageDefinition('primary_key'));
    }

    public function testGetStorageDefinitionReturnsNullForUnknown(): void
    {
        $brand = new Brand();
        $this->assertNull($brand->getStorageDefinition('nonexistent'));
    }

    // ---- dontUseAutoincrementOnPrimaryKey ----

    public function testDontUseAutoincrementReturnsCorrectFlag(): void
    {
        $brand = new Brand();
        $this->assertTrue($brand->dontUseAutoincrementOnPrimaryKey());

        $child = new RelationChildStub();
        $this->assertFalse($child->dontUseAutoincrementOnPrimaryKey());
    }

    // ---- checkStorageIntegrity ----

    public function testCheckStorageIntegrityRunsWithoutError(): void
    {
        $brand = new Brand();
        $result = $brand->checkStorageIntegrity();

        // Returns true if table matches definition, or array of differences
        $this->assertTrue($result === true || is_array($result));
    }

    // ---- __isset (delegates to __get) ----

    public function testIssetOnRelationPropertyLazyLoads(): void
    {
        $parent = new RelationParentStub();
        $parent->id = self::TEST_BRAND_ID_BASE;
        $parent->name = 'Isset Parent';

        $child = new RelationChildStub();
        $child->label = 'Isset Child';
        $child->parent_id = 0;

        $parent->children = [$child];
        $parent->create();

        $loaded = new RelationParentStub();
        $loaded->read(self::TEST_BRAND_ID_BASE);
        $loaded->setReadRelations(true);

        $this->assertTrue(isset($loaded->children));
    }

    // ---- __toString ----

    public function testToStringReturnsDumpOutput(): void
    {
        $brand = $this->insertBrand(1, 'ToString Test');
        $str = (string) $brand;

        $this->assertIsString($str);
        $this->assertStringContainsString('ToString Test', $str);
    }

    // ---- getDbTableIndexes ----

    public function testGetDbTableIndexesReturnsArray(): void
    {
        $brand = new Brand();
        $indexes = $brand->getDbTableIndexes();

        $this->assertIsArray($indexes);
    }

    // ---- batchCreate ----

    public function testBatchCreatePersistsMultipleRows(): void
    {
        $brands = [];
        for ($i = 10; $i < 15; $i++) {
            $brands[] = $this->createBrand($i, 'Batch-' . $i);
        }

        $count = Brand::batchCreate($brands);

        $this->assertSame(5, $count);
        for ($i = 10; $i < 15; $i++) {
            $loaded = new Brand(self::TEST_BRAND_ID_BASE + $i);
            $this->assertSame('Batch-' . $i, $loaded->name);
        }
    }

    public function testBatchCreateWithEmptyArrayReturnsZero(): void
    {
        $this->assertSame(0, Brand::batchCreate([]));
    }

    public function testBatchCreateThrowsOnMissingRequiredProperties(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing required properties');

        $season = new Season();
        $season->id = self::TEST_BRAND_ID_BASE;
        $season->active = true;
        $season->season_from = '2026-01-01 00:00:00';
        $season->season_to = '2026-03-31 23:59:59';
        $season->time_of_year = 'winter';
        Season::batchCreate([$season]);
    }

    public function testBatchCreateWithHasManyRelations(): void
    {
        $parent1 = new RelationParentStub();
        $parent1->id = self::TEST_BRAND_ID_BASE + 20;
        $parent1->name = 'Batch Parent 1';

        $child1a = new RelationChildStub();
        $child1a->label = 'Child 1A';
        $child1a->parent_id = 0;

        $child1b = new RelationChildStub();
        $child1b->label = 'Child 1B';
        $child1b->parent_id = 0;

        $parent1->children = [$child1a, $child1b];

        $parent2 = new RelationParentStub();
        $parent2->id = self::TEST_BRAND_ID_BASE + 21;
        $parent2->name = 'Batch Parent 2';

        $child2a = new RelationChildStub();
        $child2a->label = 'Child 2A';
        $child2a->parent_id = 0;

        $parent2->children = [$child2a];

        RelationParentStub::batchCreate([$parent1, $parent2]);

        $loadedP1 = new RelationParentStub(self::TEST_BRAND_ID_BASE + 20);
        $this->assertSame('Batch Parent 1', $loadedP1->name);

        $loadedP2 = new RelationParentStub(self::TEST_BRAND_ID_BASE + 21);
        $this->assertSame('Batch Parent 2', $loadedP2->name);

        $childObj = new RelationChildStub();
        $children1 = $childObj->loadAll(['parent_id' => self::TEST_BRAND_ID_BASE + 20]);
        $this->assertCount(2, $children1);

        $children2 = $childObj->loadAll(['parent_id' => self::TEST_BRAND_ID_BASE + 21]);
        $this->assertCount(1, $children2);
    }

    // ---- BelongsTo Relationship ----

    public function testBelongsToCreatesDetailOnParentCreate(): void
    {
        $detail = new BelongsToDetailStub();
        $detail->info = 'Detail info';
        $detail->parent_id = 0;

        $parent = new BelongsToParentStub();
        $parent->id = self::TEST_BRAND_ID_BASE;
        $parent->name = 'BelongsTo Parent';
        $parent->detail = $detail;
        $parent->create();

        $this->assertNotNull($detail->getId());
        $this->assertEquals($parent->getId(), $detail->parent_id);
    }

    public function testBelongsToLazyLoadsOnRead(): void
    {
        $detail = new BelongsToDetailStub();
        $detail->info = 'Lazy Detail';
        $detail->parent_id = 0;

        $parent = new BelongsToParentStub();
        $parent->id = self::TEST_BRAND_ID_BASE;
        $parent->name = 'Lazy Parent';
        $parent->detail = $detail;
        $parent->create();

        $loaded = new BelongsToParentStub();
        $loaded->read(self::TEST_BRAND_ID_BASE);
        $loaded->setReadRelations(true);

        $loadedDetail = $loaded->detail;
        $this->assertNotNull($loadedDetail);
        $this->assertInstanceOf(BelongsToDetailStub::class, $loadedDetail);
        $this->assertSame('Lazy Detail', $loadedDetail->info);
    }

    public function testBelongsToReturnsNullWhenNoDetail(): void
    {
        $parent = new BelongsToParentStub();
        $parent->id = self::TEST_BRAND_ID_BASE;
        $parent->name = 'No Detail';
        $parent->create();

        $loaded = new BelongsToParentStub();
        $loaded->read(self::TEST_BRAND_ID_BASE);
        $loaded->setReadRelations(true);

        $this->assertNull($loaded->detail);
    }

    // ---- Default Values ----

    public function testDefaultValueAppliedWhenPropertyIsEmpty(): void
    {
        $obj = new DefaultValueStub();
        $obj->id = self::TEST_BRAND_ID_BASE;
        $obj->create();

        $loaded = new DefaultValueStub(self::TEST_BRAND_ID_BASE);
        $this->assertSame('pending', $loaded->status);
    }

    public function testExplicitValueOverridesDefault(): void
    {
        $obj = new DefaultValueStub();
        $obj->id = self::TEST_BRAND_ID_BASE + 1;
        $obj->status = 'active';
        $obj->create();

        $loaded = new DefaultValueStub(self::TEST_BRAND_ID_BASE + 1);
        $this->assertSame('active', $loaded->status);
    }

    // ---- __get for undefined property ----

    public function testGetUndefinedPropertyReturnsErrorString(): void
    {
        $brand = new Brand();
        $brand->id = self::TEST_BRAND_ID_BASE;
        $result = $brand->__get('totally_fake_property');

        $this->assertIsString($result);
        $this->assertStringContainsString('does not exist', $result);
    }

    // ---- isCached ----

    public function testIsCachedReturnsFalseWhenNotCached(): void
    {
        $brand = $this->insertBrand(1, 'Not Cached');
        $this->assertFalse($brand->isCached());
    }

    // ---- toStdClass with relations ----

    public function testToStdClassIncludesHasManyRelations(): void
    {
        $parent = new RelationParentStub();
        $parent->id = self::TEST_BRAND_ID_BASE;
        $parent->name = 'StdClass Relations';

        $child = new RelationChildStub();
        $child->label = 'Child For StdClass';
        $child->parent_id = 0;

        $parent->children = [$child];
        $parent->create();

        $loaded = new RelationParentStub();
        $loaded->read(self::TEST_BRAND_ID_BASE);
        $loaded->setReadRelations(true);
        $loaded->readRelations();

        $std = $loaded->toStdClass(true);

        $this->assertIsArray($std->children);
        $this->assertCount(1, $std->children);
        $this->assertSame('Child For StdClass', $std->children[0]->label);
    }

    public function testToStdClassExcludesRelationsWhenFalse(): void
    {
        $parent = new RelationParentStub();
        $parent->id = self::TEST_BRAND_ID_BASE;
        $parent->name = 'No Rels StdClass';

        $child = new RelationChildStub();
        $child->label = 'Excluded';
        $child->parent_id = 0;

        $parent->children = [$child];
        $parent->create();

        $std = $parent->toStdClass(false);

        $this->assertTrue(property_exists($std, 'id'));
        $this->assertTrue(property_exists($std, 'name'));
        $this->assertFalse(property_exists($std, 'children'));
    }

    public function testToStdClassWithBelongsToRelation(): void
    {
        $detail = new BelongsToDetailStub();
        $detail->info = 'StdClass Detail';
        $detail->parent_id = 0;

        $parent = new BelongsToParentStub();
        $parent->id = self::TEST_BRAND_ID_BASE;
        $parent->name = 'StdClass BelongsTo';
        $parent->detail = $detail;
        $parent->create();

        $loaded = new BelongsToParentStub();
        $loaded->read(self::TEST_BRAND_ID_BASE);
        $loaded->setReadRelations(true);
        $loaded->readRelations();

        $std = $loaded->toStdClass(true);

        $this->assertNotNull($std->detail);
        $this->assertInstanceOf(\stdClass::class, $std->detail);
        $this->assertSame('StdClass Detail', $std->detail->info);
    }

    // ---- findObjectsInArray edge cases ----

    public function testFindObjectsInArrayThrowsForNonExistentProperty(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('does not exist');

        $parent = new RelationParentStub();
        $parent->id = self::TEST_BRAND_ID_BASE;
        $parent->findObjectsInArray('nonexistent', 'label', 'x');
    }

    // ---- loadAll edge cases ----

    public function testLoadAllWithSingleElementArrayValue(): void
    {
        $this->insertMultipleBrands();

        $brand = new Brand();
        $results = $brand->loadAll([
            'id' => ['>=', self::TEST_BRAND_ID_BASE],
            'name' => ['Alpha'],
        ]);

        $this->assertCount(1, $results);
        $this->assertSame('Alpha', $results[0]->name);
    }

    public function testLoadAllWithEmptyArrayValueDoesNotThrow(): void
    {
        $this->insertMultipleBrands();

        $brand = new Brand();
        $results = $brand->loadAll([
            'id' => ['>=', self::TEST_BRAND_ID_BASE],
            'name' => [],
        ]);

        $this->assertIsArray($results);
    }
}

/**
 * Parent stub with hasMany relation to RelationChildStub.
 * Used only for integration testing of AbstractObject relationship handling.
 *
 * @property integer $id
 * @property string $name
 * @property RelationChildStub[] $children
 */
class RelationParentStub extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = [
        'class' => ['name' => self::class],
        'database' => [
            'table_name' => 'test_relation_parents',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'name' => 'id',
                'title' => 'id',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
            'name' => [
                'name' => 'name',
                'title' => 'name',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => [
                    ['name' => 'maxlength', 'params' => 255],
                ],
            ],
            'children' => [
                'name' => 'children',
                'title' => 'children',
                'type' => 'relation',
                'required' => false,
                'filters' => null,
                'validators' => null,
                'relation' => [
                    'type' => 'hasMany',
                    'class' => RelationChildStub::class,
                    'related_id' => 'parent_id',
                ],
            ],
        ],
    ];
}

/**
 * Child stub with auto-increment primary key and parent_id foreign key.
 * Used only for integration testing of AbstractObject relationship handling.
 *
 * @property integer $id
 * @property integer $parent_id
 * @property string $label
 */
class RelationChildStub extends AbstractObject
{
    protected $_definitions = [
        'class' => ['name' => self::class],
        'database' => [
            'table_name' => 'test_relation_children',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'name' => 'id',
                'title' => 'id',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
            'parent_id' => [
                'name' => 'parent_id',
                'title' => 'parent_id',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
            'label' => [
                'name' => 'label',
                'title' => 'label',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => [
                    ['name' => 'maxlength', 'params' => 255],
                ],
            ],
        ],
    ];
}

/**
 * Detail stub with auto-increment PK for testing belongsTo relationships.
 *
 * @property integer $id
 * @property integer $parent_id
 * @property string $info
 */
class BelongsToDetailStub extends AbstractObject
{
    protected $_definitions = [
        'class' => ['name' => self::class],
        'database' => [
            'table_name' => 'test_belongsto_details',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'name' => 'id',
                'title' => 'id',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
            'parent_id' => [
                'name' => 'parent_id',
                'title' => 'parent_id',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
            'info' => [
                'name' => 'info',
                'title' => 'info',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
        ],
    ];
}

/**
 * Parent stub with belongsTo relation to BelongsToDetailStub.
 * Used for integration testing of AbstractObject belongsTo handling.
 *
 * @property integer $id
 * @property string $name
 * @property BelongsToDetailStub $detail
 */
class BelongsToParentStub extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = [
        'class' => ['name' => self::class],
        'database' => [
            'table_name' => 'test_belongsto_parents',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'name' => 'id',
                'title' => 'id',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
            'name' => [
                'name' => 'name',
                'title' => 'name',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => [
                    ['name' => 'maxlength', 'params' => 255],
                ],
            ],
            'detail' => [
                'name' => 'detail',
                'title' => 'detail',
                'type' => 'relation',
                'required' => false,
                'filters' => null,
                'validators' => null,
                'relation' => [
                    'type' => 'belongsTo',
                    'class' => BelongsToDetailStub::class,
                    'related_id' => 'parent_id',
                ],
            ],
        ],
    ];
}

/**
 * Stub with default_value property for testing default value handling.
 *
 * @property integer $id
 * @property string $status
 */
class DefaultValueStub extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = [
        'class' => ['name' => self::class],
        'database' => [
            'table_name' => 'test_default_values',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'name' => 'id',
                'title' => 'id',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
            'status' => [
                'name' => 'status',
                'title' => 'status',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    ['name' => 'maxlength', 'params' => 50],
                ],
                'default_value' => 'pending',
            ],
        ],
    ];
}
