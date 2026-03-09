<?php

namespace Pressmind\Tests\Unit\Search\Condition;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\Fulltext;

class FulltextTest extends TestCase
{
    public function testGetSqlAndValues(): void
    {
        $c = new Fulltext('hello world', ['fulltext']);
        $sql = $c->getSQL();
        $this->assertStringContainsString('MATCH', $sql);
        $this->assertStringContainsString('AGAINST', $sql);
        $values = $c->getValues();
        $this->assertNotEmpty($values);
        $this->assertSame(1, $c->getSort());
    }

    public function testGetSqlBooleanMode(): void
    {
        $c = new Fulltext('term', ['fulltext'], 'OR', 'BOOLEAN MODE');
        $sql = $c->getSQL();
        $this->assertStringContainsString('BOOLEAN MODE', $sql);
    }

    public function testGetJoins(): void
    {
        $c = new Fulltext('x');
        $this->assertStringContainsString('pmt2core_fulltext_search', $c->getJoins());
        $this->assertNull($c->getJoinType());
        $this->assertNull($c->getSubselectJoinTable());
    }

    public function testSetConfig(): void
    {
        $c = new Fulltext('a b');
        $config = new \stdClass();
        $config->search_terms = 'foo bar';
        $config->properties_to_be_queried = ['headline'];
        $config->logic_operator = 'AND';
        $config->mode = 'NATURAL LANGUAGE MODE';
        $c->setConfig($config);
        $sql = $c->getSQL();
        $this->assertStringContainsString('headline', $sql);
    }

    public function testCreate(): void
    {
        $c = Fulltext::create('test', ['fulltext']);
        $this->assertInstanceOf(Fulltext::class, $c);
    }

    public function testGetConfig(): void
    {
        $c = new Fulltext('foo bar', ['fulltext'], 'AND');
        $cfg = $c->getConfig();
        $this->assertArrayHasKey('search_terms', $cfg);
        $this->assertArrayHasKey('logic_operator', $cfg);
        $this->assertArrayHasKey('properties_to_be_queried', $cfg);
        $this->assertSame('foo bar', $cfg['search_terms']);
        $this->assertSame('AND', $cfg['logic_operator']);
    }

    public function testToJson(): void
    {
        $c = new Fulltext('a b');
        $data = $c->toJson();
        $this->assertArrayHasKey('type', $data);
        $this->assertSame('Fulltext', $data['type']);
        $this->assertArrayHasKey('config', $data);
    }

    public function testGetAdditionalFields(): void
    {
        $c = new Fulltext('x');
        $this->assertNull($c->getAdditionalFields());
    }

    public function testSetConfigWithMinimalProperties(): void
    {
        $c = new Fulltext('a');
        $config = new \stdClass();
        $config->search_terms = 'baz';
        $c->setConfig($config);
        $sql = $c->getSQL();
        $this->assertStringContainsString('fulltext', $sql);
    }
}
