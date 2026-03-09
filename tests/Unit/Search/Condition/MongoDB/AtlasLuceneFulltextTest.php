<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\AtlasLuceneFulltext;

class AtlasLuceneFulltextTest extends TestCase
{
    public function testGetType(): void
    {
        $c = new AtlasLuceneFulltext('term');
        $this->assertSame('AtlasLuceneFulltext', $c->getType());
    }

    public function testGetSearchStringRaw(): void
    {
        $c = new AtlasLuceneFulltext('  raw  ');
        $this->assertSame('  raw  ', $c->getSearchStringRaw());
    }

    public function testIsValidWithSearchString(): void
    {
        $c = new AtlasLuceneFulltext('valid');
        $this->assertTrue($c->isValid());
    }

    public function testIsValidWithEmptyString(): void
    {
        $c = new AtlasLuceneFulltext('');
        $this->assertFalse($c->isValid());
    }

    public function testGetQueryBaseReturnsEmptyWhenNotValid(): void
    {
        $c = new AtlasLuceneFulltext('');
        $query = $c->getQuery('base');
        $this->assertSame([], $query);
    }

    public function testGetQueryBaseWithSearchString(): void
    {
        $c = new AtlasLuceneFulltext('test');
        $query = $c->getQuery('base');
        $this->assertIsArray($query);
        $this->assertArrayHasKey('$search', $query);
    }

    public function testGetQueryProject(): void
    {
        $c = new AtlasLuceneFulltext('x');
        $query = $c->getQuery('project');
        $this->assertIsArray($query);
        $this->assertArrayHasKey('$project', $query);
    }

    public function testIsValidWithDefinition(): void
    {
        $definition = [
            'compound' => [
                'should' => [['text' => ['query' => '{term}', 'path' => 'fulltext']]],
                'must' => [],
            ],
        ];
        $c = new AtlasLuceneFulltext('term', $definition);
        $this->assertTrue($c->isValid());
    }

    public function testIsValidWithGeoParams(): void
    {
        $c = new AtlasLuceneFulltext('', [], 'location', 8.68, 50.11, 50, 'must');
        $this->assertTrue($c->isValid());
    }

    public function testGetQueryBaseWithCustomDefinition(): void
    {
        $definition = [
            'index' => 'custom',
            'compound' => [
                'should' => [['text' => ['query' => '{term}', 'path' => 'fulltext']]],
                'must' => [],
            ],
        ];
        $c = new AtlasLuceneFulltext('foo', $definition);
        $query = $c->getQuery('base');
        $this->assertArrayHasKey('$search', $query);
        $this->assertSame('custom', $query['$search']['index']);
        $this->assertNotEmpty($query['$search']['compound']['should']);
    }

    public function testGetQueryBaseWithGeo(): void
    {
        $c = new AtlasLuceneFulltext('x', [], 'location', 8.68, 50.11, 100, 'must');
        $query = $c->getQuery('base');
        $this->assertArrayHasKey('$search', $query);
        $this->assertArrayHasKey('compound', $query['$search']);
        $this->assertArrayHasKey('must', $query['$search']['compound']);
        $geo = $query['$search']['compound']['must'][0];
        $this->assertArrayHasKey('geoWithin', $geo);
        $this->assertSame('locations.location', $geo['geoWithin']['path']);
    }

    public function testGetQueryReturnsEmptyForUnknownType(): void
    {
        $c = new AtlasLuceneFulltext('x');
        $this->assertSame([], $c->getQuery('unknown'));
    }

    public function testIsValidWithDefinitionAndGeoParams(): void
    {
        $definition = [
            'compound' => [
                'should' => [],
                'must' => [],
            ],
        ];
        $c = new AtlasLuceneFulltext('', $definition, 'location', 8.68, 50.11, 50, 'must');
        $this->assertTrue($c->isValid());
    }

    public function testGetQueryBaseWithDefinitionAndGeo(): void
    {
        $definition = [
            'compound' => [
                'should' => [['text' => ['query' => '{term}', 'path' => 'fulltext']]],
                'must' => [],
            ],
        ];
        $c = new AtlasLuceneFulltext('test', $definition, 'location', 8.68, 50.11, 100, 'should');
        $query = $c->getQuery('base');
        $this->assertArrayHasKey('$search', $query);
        $geoEntries = $query['$search']['compound']['should'];
        $hasGeo = false;
        foreach ($geoEntries as $entry) {
            if (isset($entry['geoWithin'])) {
                $hasGeo = true;
                $this->assertSame('locations.location', $entry['geoWithin']['path']);
            }
        }
        $this->assertTrue($hasGeo);
    }

    public function testProjectQueryContainsScoreAndHighlights(): void
    {
        $c = new AtlasLuceneFulltext('test');
        $query = $c->getQuery('project');
        $project = $query['$project'];
        $this->assertSame(['$meta' => 'searchScore'], $project['score']);
        $this->assertSame(['$meta' => 'searchHighlights'], $project['highlights']);
        $this->assertSame(1, $project['prices']);
        $this->assertSame(1, $project['categories']);
    }

    public function testBaseQueryDefaultIndexAndHighlight(): void
    {
        $c = new AtlasLuceneFulltext('mallorca');
        $query = $c->getQuery('base');
        $this->assertSame('default', $query['$search']['index']);
        $this->assertArrayHasKey('highlight', $query['$search']);
        $this->assertContains('fulltext', $query['$search']['highlight']['path']);
    }

    public function testBaseQueryContainsFuzzyAndPhrase(): void
    {
        $c = new AtlasLuceneFulltext('italien');
        $query = $c->getQuery('base');
        $should = $query['$search']['compound']['should'];
        $types = array_map(fn($q) => array_key_first($q), $should);
        $this->assertContains('text', $types);
        $this->assertContains('phrase', $types);
        $this->assertContains('wildcard', $types);
    }

    public function testGeoRadiusConvertedToMeters(): void
    {
        $c = new AtlasLuceneFulltext('x', [], 'geo', 8.0, 50.0, 25);
        $query = $c->getQuery('base');
        $geo = $query['$search']['compound']['must'][0]['geoWithin']['circle'];
        $this->assertSame(25000.0, $geo['radius']);
    }
}
