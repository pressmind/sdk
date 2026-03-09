<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\Fulltext;

class FulltextTest extends TestCase
{
    public function testGetType(): void
    {
        $c = new Fulltext('test');
        $this->assertSame('Fulltext', $c->getType());
    }

    public function testGetSearchStringRaw(): void
    {
        $c = new Fulltext('  search term  ');
        $this->assertSame('  search term  ', $c->getSearchStringRaw());
    }

    public function testFirstMatchQuery(): void
    {
        $c = new Fulltext('mallorca');
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertArrayHasKey('fulltext', $query);
        $this->assertArrayHasKey('$regex', $query['fulltext']);
        $this->assertStringContainsString('mallorca', $query['fulltext']['$regex']);
    }

    public function testProjectQuery(): void
    {
        $c = new Fulltext('x');
        $query = $c->getQuery('project');
        $this->assertIsArray($query);
        $this->assertArrayHasKey('$project', $query);
    }

    public function testReturnsNullForUnknownType(): void
    {
        $c = new Fulltext('x');
        $this->assertNull($c->getQuery('unknown'));
    }

    public function testFirstMatchQueryUsesWordBoundaryRegex(): void
    {
        $c = new Fulltext('spain');
        $query = $c->getQuery('first_match');
        $this->assertStringStartsWith('\\b', $query['fulltext']['$regex']);
        $this->assertSame('i', $query['fulltext']['$options']);
    }

    public function testProjectQueryContainsExpectedFields(): void
    {
        $c = new Fulltext('test');
        $query = $c->getQuery('project');
        $project = $query['$project'];
        $this->assertSame(1, $project['id_media_object']);
        $this->assertSame(1, $project['prices']);
        $this->assertSame(1, $project['categories']);
        $this->assertSame(1, $project['code']);
        $this->assertSame(1, $project['url']);
    }

    public function testPricesFilterReturnsNull(): void
    {
        $c = new Fulltext('test');
        $this->assertNull($c->getQuery('prices_filter'));
    }
}
