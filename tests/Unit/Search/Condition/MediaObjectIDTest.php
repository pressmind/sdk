<?php

namespace Pressmind\Tests\Unit\Search\Condition;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MediaObjectID;

class MediaObjectIDTest extends TestCase
{
    public function testGetSqlAndValues(): void
    {
        $c = new MediaObjectID([1, 2, 3]);
        $sql = $c->getSQL();
        $this->assertStringContainsString('IN (', $sql);
        $this->assertStringContainsString('id', $sql);
        $values = $c->getValues();
        $this->assertCount(3, $values);
        $this->assertSame(6, $c->getSort());
    }

    public function testGetJoinsNull(): void
    {
        $c = new MediaObjectID([1]);
        $this->assertNull($c->getJoins());
        $this->assertNull($c->getJoinType());
        $this->assertNull($c->getSubselectJoinTable());
        $this->assertNull($c->getAdditionalFields());
    }

    public function testSetConfigAndGetConfig(): void
    {
        $c = new MediaObjectID([1]);
        $config = new \stdClass();
        $config->ids = [10, 20];
        $c->setConfig($config);
        $this->assertSame(['ids' => [10, 20]], $c->getConfig());
    }

    public function testToJsonAndCreate(): void
    {
        $c = MediaObjectID::create([1]);
        $this->assertInstanceOf(MediaObjectID::class, $c);
        $json = $c->toJson();
        $this->assertSame('MediaObjectID', $json['type']);
    }
}
