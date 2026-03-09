<?php

namespace Pressmind\Tests\Unit\Search\Condition;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\Text;

class TextTest extends TestCase
{
    public function testGetSqlAndValues(): void
    {
        $c = new Text(100, 'hello world', ['headline' => 'LIKE']);
        $sql = $c->getSQL();
        $this->assertStringContainsString('LIKE', $sql);
        $values = $c->getValues();
        $this->assertNotEmpty($values);
        $this->assertSame(1, $c->getSort());
    }

    public function testGetJoins(): void
    {
        $c = new Text(100, 'x', ['headline' => 'LIKE']);
        $this->assertStringContainsString('objectdata_100', $c->getJoins());
        $this->assertNull($c->getJoinType());
        $this->assertNull($c->getSubselectJoinTable());
    }

    public function testSetConfigAndGetConfig(): void
    {
        $c = new Text(100, 'a b');
        $config = new \stdClass();
        $config->object_type_id = 200;
        $config->search_terms = 'foo bar';
        $config->properties_to_be_queried = ['headline' => 'LIKE'];
        $config->logic_operator = 'OR';
        $c->setConfig($config);
        $cfg = $c->getConfig();
        $this->assertSame(200, $cfg['object_type_id']);
        $this->assertSame('foo bar', $cfg['search_terms']);
    }

    public function testToJsonAndCreate(): void
    {
        $c = Text::create(100, 'test', ['headline' => 'LIKE']);
        $this->assertInstanceOf(Text::class, $c);
        $json = $c->toJson();
        $this->assertSame('Text', $json['type']);
    }

    public function testGetAdditionalFields(): void
    {
        $c = new Text(100, 'x', ['headline' => 'LIKE']);
        $this->assertNull($c->getAdditionalFields());
    }

    public function testSetConfigWithDefaultLogicOperator(): void
    {
        $c = new Text();
        $config = new \stdClass();
        $config->object_type_id = 100;
        $config->search_terms = 'hello';
        $config->properties_to_be_queried = ['headline' => '='];
        $c->setConfig($config);
        $cfg = $c->getConfig();
        $this->assertSame('AND', $cfg['logic_operator']);
    }

    public function testGetSqlWithEqualsOperator(): void
    {
        $c = new Text(100, 'term', ['code' => '=']);
        $sql = $c->getSQL();
        $this->assertStringContainsString('code = ', $sql);
        $this->assertStringNotContainsString('LIKE', $sql);
    }
}
