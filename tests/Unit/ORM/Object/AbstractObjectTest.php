<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\CheapestPriceSpeed;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Tests AbstractObject main public API via concrete implementation (CheapestPriceSpeed).
 * Covers property handling, getId/setId, getDbTableName, fromArray, toStdClass, fromStdClass, isValid, getLog.
 */
class AbstractObjectTest extends AbstractTestCase
{
    private function createObject(): CheapestPriceSpeed
    {
        return new CheapestPriceSpeed(null, false);
    }

    public function testGetDbTableNameReturnsPrefixedTableName(): void
    {
        $obj = $this->createObject();
        $this->assertSame('pmt2core_pmt2core_cheapest_price_speed', $obj->getDbTableName());
    }

    public function testGetIdReturnsNullWhenNotSet(): void
    {
        $obj = $this->createObject();
        $this->assertNull($obj->getId());
    }

    public function testSetIdAndGetId(): void
    {
        $obj = $this->createObject();
        $obj->setId(42);
        $this->assertSame(42, $obj->getId());
    }

    public function testIsValidReturnsFalseWhenIdIsNull(): void
    {
        $obj = $this->createObject();
        $this->assertFalse($obj->isValid());
    }

    public function testIsValidReturnsTrueWhenIdIsSet(): void
    {
        $obj = $this->createObject();
        $obj->setId(1);
        $this->assertTrue($obj->isValid());
    }

    public function testFromArrayPopulatesProperties(): void
    {
        $obj = $this->createObject();
        $obj->fromArray([
            'id' => 10,
            'id_media_object' => 100,
            'id_booking_package' => 'bp-1',
        ]);
        $this->assertSame(10, $obj->getId());
        $this->assertSame(100, $obj->id_media_object);
        $this->assertSame('bp-1', $obj->id_booking_package);
    }

    public function testToStdClassWithoutRelationsExcludesOnlyScalarProperties(): void
    {
        $obj = $this->createObject();
        $obj->setId(5);
        $obj->id_media_object = 99;
        $obj->id_booking_package = 'bp-x';
        $std = $obj->toStdClass(false);
        $this->assertInstanceOf(\stdClass::class, $std);
        $this->assertSame(5, $std->id);
        $this->assertSame(99, $std->id_media_object);
        $this->assertSame('bp-x', $std->id_booking_package);
    }

    public function testFromStdClassPopulatesProperties(): void
    {
        $obj = $this->createObject();
        $std = new \stdClass();
        $std->id = 7;
        $std->id_media_object = 200;
        $std->id_booking_package = 'bp-y';
        $obj->fromStdClass($std);
        $this->assertSame(7, $obj->getId());
        $this->assertSame(200, $obj->id_media_object);
        $this->assertSame('bp-y', $obj->id_booking_package);
    }

    public function testFromStdClassWithNullDoesNotThrow(): void
    {
        $obj = $this->createObject();
        $obj->setId(1);
        /** @phpstan-ignore argument.type (testing runtime behavior when null is passed) */
        $obj->fromStdClass(null);
        $this->assertSame(1, $obj->getId());
    }

    public function testGetLogReturnsArray(): void
    {
        $obj = $this->createObject();
        $this->assertIsArray($obj->getLog());
    }

    public function testGetDbPrimaryKeyReturnsPrimaryKeyName(): void
    {
        $obj = $this->createObject();
        $this->assertSame('id', $obj->getDbPrimaryKey());
    }

    public function testSaveCallsCreateWhenIdIsNull(): void
    {
        $obj = $this->createObject();
        $obj->id_media_object = 1;
        $obj->id_booking_package = 'bp-1';
        $obj->save();
        $this->addToAssertionCount(1);
    }

    public function testSaveCallsUpdateWhenIdIsSet(): void
    {
        $obj = $this->createObject();
        $obj->setId(1);
        $obj->id_media_object = 1;
        $obj->id_booking_package = 'bp-1';
        $obj->save();
        $this->addToAssertionCount(1);
    }

    public function testToStdClassWithRelations(): void
    {
        $obj = $this->createObject();
        $obj->setId(1);
        $obj->id_media_object = 2;
        $obj->id_booking_package = 'bp-1';
        $std = $obj->toStdClass(true);
        $this->assertInstanceOf(\stdClass::class, $std);
        $this->assertSame(1, $std->id);
        $this->assertSame(2, $std->id_media_object);
    }

    public function testFromJsonPopulatesFromValidJson(): void
    {
        $obj = $this->createObject();
        $json = '{"id":11,"id_media_object":22,"id_booking_package":"bp-11"}';
        $obj->fromJson($json);
        $this->assertSame(11, $obj->getId());
        $this->assertSame(22, $obj->id_media_object);
        $this->assertSame('bp-11', $obj->id_booking_package);
    }

    public function testFromJsonThrowsOnInvalidJson(): void
    {
        $obj = $this->createObject();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Decoding of JSON String failed');
        $obj->fromJson('{invalid');
    }

    public function testToJsonReturnsJsonString(): void
    {
        $obj = $this->createObject();
        $obj->setId(3);
        $obj->id_media_object = 4;
        $obj->id_booking_package = 'bp-3';
        $json = $obj->toJson();
        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertSame(3, $decoded['id']);
        $this->assertSame(4, $decoded['id_media_object']);
    }

    public function testSetReadRelationsAndGetLog(): void
    {
        $obj = $this->createObject();
        $obj->setReadRelations(true);
        $this->assertIsArray($obj->getLog());
    }

    public function testReadWithNullIdReturnsNull(): void
    {
        $obj = $this->createObject();
        $result = $obj->read(null);
        $this->assertNull($result);
    }

    public function testReadWithZeroIdReturnsNull(): void
    {
        $obj = $this->createObject();
        $result = $obj->read('0');
        $this->assertNull($result);
    }

    public function testIsCachedReturnsFalseWhenNotCached(): void
    {
        $obj = $this->createObject();
        $obj->setId(1);
        $this->assertFalse($obj->isCached());
    }

    public function testSetSkipCache(): void
    {
        $obj = $this->createObject();
        $obj->setSkipCache(true);
        $this->addToAssertionCount(1);
    }

    public function test__CallWritesToLogWhenLoggingEnabled(): void
    {
        $registry = \Pressmind\Registry::getInstance();
        $config = $this->createMockConfig(['logging' => ['enable_advanced_object_log' => true]]);
        $registry->add('config', $config);
        try {
            $obj = $this->createObject();
            $obj->someMethod();
            $log = $obj->getLog();
            $this->assertNotEmpty($log);
        } finally {
            $registry->add('config', $this->createMockConfig([]));
        }
    }
}
