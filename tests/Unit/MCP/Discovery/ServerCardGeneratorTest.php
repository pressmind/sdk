<?php

namespace Pressmind\Tests\Unit\MCP\Discovery;

use Pressmind\MCP\Discovery\ServerCardGenerator;
use Pressmind\MCP\Discovery\ToolMetadataProvider;
use Pressmind\Tests\Unit\AbstractTestCase;

class ServerCardGeneratorTest extends AbstractTestCase
{
    public function testBuildsMcpServerCardWithStaticToolMetadata(): void
    {
        $provider = new ToolMetadataProvider();
        $generator = new ServerCardGenerator($provider);

        $card = $generator->build([
            'site_url' => 'https://reisen.example.com/',
            'mcp_prefix' => 'mcp',
            'server_name' => 'pressmind-travel-reisen-example-com',
            'server_title' => 'Reisen Example Travel Search',
            'description' => 'Search and book travel products.',
            'version' => '1.2.3',
        ]);

        $this->assertSame('https://static.modelcontextprotocol.io/schemas/mcp-server-card/v1.json', $card['$schema']);
        $this->assertSame('1.0', $card['version']);
        $this->assertSame('2025-06-18', $card['protocolVersion']);
        $this->assertSame('pressmind-travel-reisen-example-com', $card['serverInfo']['name']);
        $this->assertSame('Reisen Example Travel Search', $card['serverInfo']['title']);
        $this->assertSame('1.2.3', $card['serverInfo']['version']);
        $this->assertSame('sse', $card['transport']['type']);
        $this->assertSame('https://reisen.example.com/mcp/sse', $card['transport']['endpoint']);
        $this->assertArrayHasKey('tools', $card['capabilities']);

        $toolNames = array_column($card['tools'], 'name');
        $this->assertContains('search', $toolNames);
        $this->assertContains('fetch', $toolNames);
        $this->assertContains('get_calendar', $toolNames);

        foreach ($card['tools'] as $tool) {
            $this->assertArrayHasKey('description', $tool);
            $this->assertArrayHasKey('inputSchema', $tool);
            $this->assertSame('object', $tool['inputSchema']['type']);
        }
    }

    public function testAuthMetadataIsOnlyWrittenWhenConfigured(): void
    {
        $generator = new ServerCardGenerator(new ToolMetadataProvider());

        $withoutAuth = $generator->build([
            'site_url' => 'https://reisen.example.com',
        ]);
        $this->assertArrayNotHasKey('authentication', $withoutAuth);

        $withAuth = $generator->build([
            'site_url' => 'https://reisen.example.com',
            'auth_required' => true,
            'auth_schemes' => ['bearer', 'oauth2'],
        ]);

        $this->assertSame([
            'required' => true,
            'schemes' => ['bearer', 'oauth2'],
        ], $withAuth['authentication']);
    }

    public function testEmptyServerNameFallsBackToDomainBasedName(): void
    {
        $generator = new ServerCardGenerator(new ToolMetadataProvider());

        $card = $generator->build([
            'site_url' => 'https://reisen.example.com',
            'server_name' => '',
        ]);

        $this->assertSame('pressmind-travel-reisen-example-com', $card['serverInfo']['name']);
    }
}
