<?php

namespace Pressmind\Tests\Unit\Search\Condition;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\Search\Condition\ObjectType;

class ObjectTypeTest extends AbstractTestCase
{
    public function testGetSqlSingleId(): void
    {
        $c = new ObjectType(100);
        $sql = $c->getSQL();
        $this->assertStringContainsString('id_object_type = :object_type_id', $sql);
        $values = $c->getValues();
        $this->assertSame(100, $values[':object_type_id']);
        $this->assertSame(4, $c->getSort());
    }

    public function testGetSqlMultipleIds(): void
    {
        $c = new ObjectType([100, 200]);
        $sql = $c->getSQL();
        $this->assertStringContainsString('IN (', $sql);
        $values = $c->getValues();
        $this->assertCount(2, $values);
    }

    public function testGetJoinsNull(): void
    {
        $c = new ObjectType(1);
        $this->assertNull($c->getJoins());
        $this->assertNull($c->getJoinType());
        $this->assertNull($c->getSubselectJoinTable());
        $this->assertNull($c->getAdditionalFields());
    }

    public function testSetConfig(): void
    {
        $c = new ObjectType(1);
        $config = new \stdClass();
        $config->object_type_id = 200;
        $c->setConfig($config);
        $values = $c->getValues();
        $this->assertSame(200, $values[':object_type_id']);
    }

    public function testCreateWithInt(): void
    {
        $c = ObjectType::create(100);
        $this->assertInstanceOf(ObjectType::class, $c);
    }

    public function testCreateWithArray(): void
    {
        $c = ObjectType::create([100, 200]);
        $this->assertInstanceOf(ObjectType::class, $c);
    }

    public function testCreateWithStringResolvesFromRegistry(): void
    {
        $config = $this->createMockConfig([
            'data' => ['media_types' => [100 => 'Reise', 200 => 'Hotel']],
        ]);
        \Pressmind\Registry::getInstance()->add('config', $config);

        $c = ObjectType::create('Reise');
        $values = $c->getValues();
        $this->assertSame(100, $values[':object_type_id']);
    }

    public function testCreateWithStringArrayResolvesFromRegistry(): void
    {
        $config = $this->createMockConfig([
            'data' => ['media_types' => [100 => 'Reise', 200 => 'Hotel']],
        ]);
        \Pressmind\Registry::getInstance()->add('config', $config);

        $c = ObjectType::create(['Reise', 'Hotel']);
        $values = $c->getValues();
        $this->assertCount(2, $values);
        $this->assertSame(100, $values[':object_type_id0']);
        $this->assertSame(200, $values[':object_type_id1']);
    }

    public function testCreateWithMixedArrayResolvesFromRegistry(): void
    {
        $config = $this->createMockConfig([
            'data' => ['media_types' => [100 => 'Reise']],
        ]);
        \Pressmind\Registry::getInstance()->add('config', $config);

        $c = ObjectType::create([300, 'Reise']);
        $values = $c->getValues();
        $this->assertCount(2, $values);
        $this->assertSame(300, $values[':object_type_id0']);
        $this->assertSame(100, $values[':object_type_id1']);
    }
}
