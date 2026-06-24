<?php

declare(strict_types=1);

namespace Pressmind\MCP\Discovery;

class ServerCardGenerator
{
    public const DEFAULT_PROTOCOL_VERSION = '2025-06-18';
    public const DEFAULT_SCHEMA = 'https://static.modelcontextprotocol.io/schemas/mcp-server-card/v1.json';

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
        $mcpPrefix = trim((string) ($options['mcp_prefix'] ?? 'mcp'), '/');
        if ($mcpPrefix === '') {
            $mcpPrefix = 'mcp';
        }

        $serverName = $this->stringOption($options, 'server_name', $this->defaultServerName($siteUrl));
        $serverTitle = $this->stringOption($options, 'server_title', 'Pressmind Travel MCP');
        $version = $this->stringOption($options, 'version', '1.0.0');

        $card = [
            '$schema' => self::DEFAULT_SCHEMA,
            'version' => '1.0',
            'protocolVersion' => self::DEFAULT_PROTOCOL_VERSION,
            'serverInfo' => [
                'name' => $serverName,
                'title' => $serverTitle,
                'version' => $version,
            ],
            'description' => (string) ($options['description'] ?? 'Search and retrieve Pressmind travel products via MCP.'),
            'transport' => [
                'type' => 'sse',
                'endpoint' => $siteUrl . '/' . $mcpPrefix . '/sse',
            ],
            'capabilities' => [
                'tools' => new \stdClass(),
            ],
            'tools' => $this->toolMetadataProvider->getTools(),
            'resources' => 'dynamic',
            'prompts' => 'dynamic',
        ];

        if (!empty($options['documentation_url'])) {
            $card['documentationUrl'] = (string) $options['documentation_url'];
        }
        if (!empty($options['icon_url'])) {
            $card['iconUrl'] = (string) $options['icon_url'];
        }
        if (!empty($options['instructions'])) {
            $card['instructions'] = (string) $options['instructions'];
        }
        if (!empty($options['auth_required'])) {
            $schemes = $options['auth_schemes'] ?? ['bearer'];
            if (is_string($schemes)) {
                $schemes = array_filter(array_map('trim', explode(',', $schemes)));
            }
            $card['authentication'] = [
                'required' => true,
                'schemes' => array_values((array) $schemes),
            ];
        }

        return $card;
    }

    /**
     * @param array<string, mixed> $card
     */
    public function toJson(array $card): string
    {
        return json_encode($card, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    }

    private function normalizeSiteUrl(string $siteUrl): string
    {
        $siteUrl = trim($siteUrl);
        if ($siteUrl === '') {
            throw new \InvalidArgumentException('Missing site_url.');
        }

        return rtrim($siteUrl, '/');
    }

    private function defaultServerName(string $siteUrl): string
    {
        $host = parse_url($siteUrl, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            $host = 'localhost';
        }

        $name = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $host) ?? 'localhost');
        $name = trim($name, '-');

        return 'pressmind-travel-' . ($name !== '' ? $name : 'localhost');
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
