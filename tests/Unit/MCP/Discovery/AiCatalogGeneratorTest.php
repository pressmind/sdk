<?php

namespace Pressmind\Tests\Unit\MCP\Discovery;

use Pressmind\MCP\Discovery\AiCatalogGenerator;
use Pressmind\MCP\Discovery\ToolMetadataProvider;
use Pressmind\Tests\Unit\AbstractTestCase;

class AiCatalogGeneratorTest extends AbstractTestCase
{
    public function testBuildsArdCatalogForMcpServerCard(): void
    {
        $generator = new AiCatalogGenerator(new ToolMetadataProvider());

        $catalog = $generator->build([
            'site_url' => 'https://reisen.example.com',
            'server_title' => 'Reisen Example Travel Search',
            'server_name' => 'pressmind-travel-reisen-example-com',
            'description' => 'Search and book travel products.',
            'version' => '1.2.3',
        ]);

        $this->assertSame('1.0', $catalog['specVersion']);
        $this->assertSame('Reisen Example Travel Search', $catalog['host']['displayName']);
        $this->assertSame('did:web:reisen.example.com', $catalog['host']['identifier']);
        $this->assertCount(1, $catalog['entries']);

        $entry = $catalog['entries'][0];
        $this->assertSame('urn:air:reisen.example.com:mcp:travel-search', $entry['identifier']);
        $this->assertSame('Reisen Example Travel Search', $entry['displayName']);
        $this->assertSame('application/mcp-server-card+json', $entry['type']);
        $this->assertSame('https://reisen.example.com/.well-known/mcp/server-card.json', $entry['url']);
        $this->assertArrayNotHasKey('data', $entry);
        $this->assertSame('1.2.3', $entry['version']);
        $this->assertContains('search', $entry['capabilities']);
        $this->assertContains('travel', $entry['tags']);
        $this->assertGreaterThanOrEqual(2, count($entry['representativeQueries']));
        $this->assertLessThanOrEqual(5, count($entry['representativeQueries']));
    }

    public function testJsonOutputKeepsUnicodeAndReadableUrls(): void
    {
        $generator = new AiCatalogGenerator(new ToolMetadataProvider());

        $json = $generator->toJson($generator->build([
            'site_url' => 'https://büro.example.com',
            'server_title' => 'Büro Reisen',
            'description' => 'Reisen für Städte und Küsten.',
        ]));

        $this->assertJson($json);
        $this->assertStringContainsString('Büro Reisen', $json);
        $this->assertStringContainsString('https://büro.example.com/.well-known/mcp/server-card.json', $json);
        $this->assertStringNotContainsString('https:\\/\\/', $json);
    }

    public function testEmptyServerTitleFallsBackToDefaultDisplayName(): void
    {
        $generator = new AiCatalogGenerator(new ToolMetadataProvider());

        $catalog = $generator->build([
            'site_url' => 'https://reisen.example.com',
            'server_title' => '',
        ]);

        $this->assertSame('Pressmind Travel MCP', $catalog['host']['displayName']);
        $this->assertSame('Pressmind Travel MCP', $catalog['entries'][0]['displayName']);
    }
}
