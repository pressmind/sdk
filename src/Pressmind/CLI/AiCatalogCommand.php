<?php

declare(strict_types=1);

namespace Pressmind\CLI;

use Pressmind\MCP\Discovery\AiCatalogGenerator;
use Pressmind\MCP\Discovery\ServerCardGenerator;

class AiCatalogCommand extends AbstractCommand
{
    protected function execute(): int
    {
        if ($this->hasOption('help')) {
            $this->printHelp();
            return 0;
        }

        try {
            $context = $this->resolveContext();
            $options = $this->buildGeneratorOptions($context);

            $catalogGenerator = new AiCatalogGenerator();
            $serverCardGenerator = new ServerCardGenerator();
            $catalogJson = $catalogGenerator->toJson($catalogGenerator->build($options));
            $serverCardJson = $serverCardGenerator->toJson($serverCardGenerator->build($options));

            if ($this->hasOption('dry-run')) {
                $this->output->info('Dry run: no files written.');
                $this->output->writeln('AI Catalog: ' . $context['catalog_path'], null);
                $this->output->writeln('MCP Server Card: ' . $context['server_card_path'], null);
                $this->output->writeln('robots.txt: ' . $context['robots_path'], null);
                return 0;
            }

            $this->writeJsonFile($context['catalog_path'], $catalogJson);
            $this->writeJsonFile($context['server_card_path'], $serverCardJson);
            $this->ensureRobotsAgentmap($context['robots_path'], $options['site_url'] . '/.well-known/ai-catalog.json');

            $this->output->success('AI Catalog written to ' . $context['catalog_path']);
            $this->output->success('MCP Server Card written to ' . $context['server_card_path']);
            $this->output->success('robots.txt Agentmap present in ' . $context['robots_path']);
            return 0;
        } catch (\Throwable $e) {
            $this->output->error($e->getMessage());
            return 1;
        }
    }

    private function printHelp(): void
    {
        echo <<<HELP
Pressmind ARD / AI Catalog generator

Usage:
  php bin/ai-catalog [--application-path=DIR] [--pm-config=FILE] [--env=ENV]
  php bin/ai-catalog --application-path=/path/to/app --document-root=/path/to/httpdocs

Options:
  --application-path=DIR   Directory containing pm-config.php (default: current working directory)
  --pm-config=FILE         Config basename (default: PM_CONFIG env or pm-config.php)
  --env=ENV                Config environment (default: APP_ENV env or development)
  --document-root=DIR      Public document root (default: server.document_root from pm-config)
  --site-url=URL           Public site URL (default: mcp.site_url or server.webserver_http)
  --mcp-prefix=PREFIX      Public MCP URL prefix (default: mcp)
  --server-name=NAME       MCP server machine name
  --server-title=TITLE     MCP server display title
  --description=TEXT       Discovery description
  --version=VERSION        Discovery artifact version
  --auth-required          Include authentication metadata in server-card.json
  --auth-schemes=LIST      Comma-separated auth schemes (default: bearer)
  --dry-run                Validate and print target paths without writing files
  --help                   Show this message

Writes:
  <document-root>/.well-known/ai-catalog.json
  <document-root>/.well-known/mcp/server-card.json
  <document-root>/robots.txt

HELP;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveContext(): array
    {
        $applicationPath = $this->getStringOption('application-path', getcwd() ?: '');
        $applicationPath = rtrim($applicationPath, DIRECTORY_SEPARATOR);
        if (!is_dir($applicationPath)) {
            throw new \InvalidArgumentException('Application path is not a directory: ' . $applicationPath);
        }

        $pmConfig = $this->getStringOption('pm-config', getenv('PM_CONFIG') ?: 'pm-config.php');
        $configPath = $applicationPath . DIRECTORY_SEPARATOR . $pmConfig;
        if (!is_file($configPath)) {
            throw new \InvalidArgumentException('Config file not found: ' . $configPath);
        }

        $env = $this->getStringOption('env', getenv('APP_ENV') ?: 'development');
        $allConfig = $this->loadConfigFile($configPath);
        $config = $this->configForEnvironment($allConfig, $env);

        $basePath = dirname($applicationPath);
        $configuredDocumentRoot = $this->getStringOption(
            'document-root',
            (string) ($config['server']['document_root'] ?? $applicationPath)
        );
        $documentRoot = $this->replacePathTokens($configuredDocumentRoot, $applicationPath, $basePath, $applicationPath);
        $documentRoot = rtrim($documentRoot, DIRECTORY_SEPARATOR);
        if ($documentRoot === '') {
            throw new \InvalidArgumentException('Document root is empty.');
        }
        if (!is_dir($documentRoot)) {
            throw new \InvalidArgumentException('Document root is not a directory: ' . $documentRoot);
        }

        return [
            'application_path' => $applicationPath,
            'base_path' => $basePath,
            'document_root' => $documentRoot,
            'config' => $config,
            'catalog_path' => $documentRoot . DIRECTORY_SEPARATOR . '.well-known' . DIRECTORY_SEPARATOR . 'ai-catalog.json',
            'server_card_path' => $documentRoot . DIRECTORY_SEPARATOR . '.well-known' . DIRECTORY_SEPARATOR . 'mcp' . DIRECTORY_SEPARATOR . 'server-card.json',
            'robots_path' => $documentRoot . DIRECTORY_SEPARATOR . 'robots.txt',
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildGeneratorOptions(array $context): array
    {
        $config = $context['config'];
        $mcp = isset($config['mcp']) && is_array($config['mcp']) ? $config['mcp'] : [];
        $siteUrl = $this->getStringOption(
            'site-url',
            (string) ($mcp['site_url'] ?? ($config['server']['webserver_http'] ?? ''))
        );
        $siteUrl = rtrim(trim($siteUrl), '/');
        if ($siteUrl === '' || !preg_match('#^https?://#', $siteUrl)) {
            throw new \InvalidArgumentException('Invalid site-url. Use an absolute http(s) URL.');
        }

        $authSchemes = $this->getStringOption('auth-schemes', 'bearer');

        return [
            'site_url' => $siteUrl,
            'mcp_prefix' => $this->getStringOption('mcp-prefix', (string) ($mcp['mcp_prefix'] ?? 'mcp')),
            'server_name' => $this->getStringOption('server-name', (string) ($mcp['server_name'] ?? '')),
            'server_title' => $this->getStringOption('server-title', (string) ($mcp['server_title'] ?? 'Pressmind Travel MCP')),
            'description' => $this->getStringOption('description', (string) ($mcp['description'] ?? 'Search and retrieve Pressmind travel products via MCP.')),
            'version' => $this->getStringOption('version', (string) ($mcp['version'] ?? '1.0.0')),
            'documentation_url' => (string) ($mcp['documentation_url'] ?? ''),
            'icon_url' => (string) ($mcp['icon_url'] ?? ''),
            'instructions' => (string) ($mcp['instructions'] ?? ''),
            'auth_required' => $this->hasOption('auth-required'),
            'auth_schemes' => array_values(array_filter(array_map('trim', explode(',', $authSchemes)))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadConfigFile(string $configPath): array
    {
        $config = null;
        $loaded = include $configPath;
        if (is_array($loaded)) {
            return $loaded;
        }
        if (is_array($config)) {
            return $config;
        }

        throw new \InvalidArgumentException('Config file did not define a config array.');
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function configForEnvironment(array $config, string $env): array
    {
        if (!isset($config['development']) || !is_array($config['development'])) {
            return $config;
        }

        $development = $config['development'];
        if ($env === 'development') {
            return $development;
        }

        $environment = isset($config[$env]) && is_array($config[$env]) ? $config[$env] : [];
        return array_replace_recursive($development, $environment);
    }

    private function writeJsonFile(string $path, string $json): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create directory: ' . $dir);
        }

        $tmp = tempnam($dir, '.ai-catalog-');
        if ($tmp === false) {
            throw new \RuntimeException('Could not create temporary file in: ' . $dir);
        }

        if (file_put_contents($tmp, $json) === false) {
            @unlink($tmp);
            throw new \RuntimeException('Could not write temporary file: ' . $tmp);
        }

        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Could not move temporary file to: ' . $path);
        }
    }

    private function ensureRobotsAgentmap(string $robotsPath, string $catalogUrl): void
    {
        $line = 'Agentmap: ' . $catalogUrl;
        $content = '';
        if (is_file($robotsPath)) {
            $read = file_get_contents($robotsPath);
            if ($read === false) {
                throw new \RuntimeException('Could not read robots.txt: ' . $robotsPath);
            }
            $content = $read;
        }

        $lines = preg_split('/\r\n|\n|\r/', $content);
        if (is_array($lines)) {
            foreach ($lines as $existingLine) {
                if (trim($existingLine) === $line) {
                    return;
                }
            }
        }

        if ($content !== '' && !str_ends_with($content, "\n") && !str_ends_with($content, "\r")) {
            $content .= PHP_EOL;
        }
        if ($content !== '' && !str_ends_with($content, PHP_EOL . PHP_EOL)) {
            $content .= PHP_EOL;
        }
        $content .= $line . PHP_EOL;

        if (file_put_contents($robotsPath, $content) === false) {
            throw new \RuntimeException('Could not write robots.txt: ' . $robotsPath);
        }
    }

    private function getStringOption(string $name, string $default): string
    {
        $value = $this->getOption($name, $default);
        if ($value === true || $value === null) {
            return $default;
        }

        return (string) $value;
    }

    private function replacePathTokens(string $path, string $applicationPath, string $basePath, string $documentRoot): string
    {
        return str_replace(
            ['APPLICATION_PATH', 'BASE_PATH', 'WEBSERVER_DOCUMENT_ROOT'],
            [$applicationPath, $basePath, $documentRoot],
            $path
        );
    }
}
