<?php

declare(strict_types=1);

namespace Pressmind\MCP\Discovery;

class AiCatalogGenerator
{
    private ToolMetadataProvider $toolMetadataProvider;

    public function __construct(?ToolMetadataProvider $toolMetadataProvider = null)
    {
        $this->toolMetadataProvider = $toolMetadataProvider ?? new ToolMetadataProvider();
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function build(array $options): array
    {
        $siteUrl = $this->normalizeSiteUrl((string) ($options['site_url'] ?? ''));
        $host = $this->extractHost($siteUrl);
        $serverTitle = $this->stringOption($options, 'server_title', 'Pressmind Travel MCP');
        $version = $this->stringOption($options, 'version', '1.0.0');

        return [
            'specVersion' => '1.0',
            'host' => [
                'displayName' => $serverTitle,
                'identifier' => 'did:web:' . $host,
            ],
            'entries' => [
                [
                    'identifier' => 'urn:air:' . $host . ':mcp:travel-search',
                    'displayName' => $serverTitle,
                    'type' => 'application/mcp-server-card+json',
                    'url' => $siteUrl . '/.well-known/mcp/server-card.json',
                    'description' => (string) ($options['description'] ?? 'Search and retrieve Pressmind travel products via MCP.'),
                    'tags' => ['travel', 'tourism', 'booking', 'pressmind', 'mcp'],
                    'capabilities' => $this->capabilityNames(),
                    'representativeQueries' => [
                        'find package holidays for next month',
                        'show travel products for a destination',
                        'load prices and booking options for a trip',
                    ],
                    'version' => $version,
                    'updatedAt' => gmdate('c'),
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $catalog
     */
    public function toJson(array $catalog): string
    {
        return json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    }

    /**
     * @return array<int, string>
     */
    private function capabilityNames(): array
    {
        $names = [];
        foreach ($this->toolMetadataProvider->getTools() as $tool) {
            if (isset($tool['name']) && is_string($tool['name']) && $tool['name'] !== '') {
                $names[] = $tool['name'];
            }
        }

        return $names;
    }

    private function normalizeSiteUrl(string $siteUrl): string
    {
        $siteUrl = trim($siteUrl);
        if ($siteUrl === '') {
            throw new \InvalidArgumentException('Missing site_url.');
        }

        return rtrim($siteUrl, '/');
    }

    private function extractHost(string $siteUrl): string
    {
        $host = parse_url($siteUrl, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            throw new \InvalidArgumentException('Invalid site_url: ' . $siteUrl);
        }

        return strtolower($host);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function stringOption(array $options, string $key, string $default): string
    {
        $value = trim((string) ($options[$key] ?? ''));
        return $value !== '' ? $value : $default;
    }
}
